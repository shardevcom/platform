<?php

declare(strict_types=1);

namespace Shardevcom\Security\Http\Controllers;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Shardevcom\Core\Contracts\SecondFactorGate;
use Shardevcom\Core\Results\ApiError;
use Shardevcom\Core\Results\ApiResponse;
use Shardevcom\Security\Authentication\AccessTokenIssuer;
use Shardevcom\Security\Authentication\UserPresenter;
use Shardevcom\Security\Infrastructure\AccountLock;

/**
 * First-party token login adapter (demo/showcase surface).
 *
 * Exchanges email + password for a Passport personal access token so a
 * first-party SPA can talk to the platform API. It is deliberately an
 * adapter: it authenticates credentials against the platform's own provider,
 * mints a token through the model (Passport storage), and frames the result
 * with the shared envelope. No permission decision lives here — the token
 * carries the platform's scope vocabulary and the repository/lifecycle still
 * authorizes every operation.
 *
 * The platform is resolved from the `{guard}` route default the provider sets
 * when it registers these routes per prefix (web/admin/employee), so the same
 * controller serves every platform without a second copy.
 */
final class AuthController
{
    public function __construct(
        private readonly AuthManager $auth,
        private readonly ConfigRepository $config,
        private readonly AccessTokenIssuer $tokens,
        private readonly UserPresenter $presenter,
        private readonly AccountLock $locks,
        private readonly SecondFactorGate $secondFactor,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $platform = $this->platform($request);

        if ($platform === null) {
            return $this->error('PLATFORM_NOT_FOUND', 'Unknown platform.', [], 404);
        }

        $email = $request->input('email');
        $password = $request->input('password');

        $errors = [];

        if (! is_string($email) || $email === '') {
            $errors['email'][] = 'The email field is required.';
        }

        if (! is_string($password) || $password === '') {
            $errors['password'][] = 'The password field is required.';
        }

        if ($errors !== []) {
            return $this->error('VALIDATION_FAILED', 'The given data was invalid.', $errors, 422);
        }

        $provider = $this->auth->createUserProvider($platform['provider']);
        $user = $provider?->retrieveByCredentials(['email' => $email]);

        if ($user === null || ! $provider->validateCredentials($user, ['password' => $password])) {
            return $this->error('INVALID_CREDENTIALS', 'These credentials do not match our records.', [], 401);
        }

        // Checked after credentials so a disabled account is not revealed to
        // anyone who does not already know its password.
        if ($this->locks->isDisabled($platform['guard'], $user->getAuthIdentifier())) {
            return $this->error('ACCOUNT_DISABLED', 'This account has been disabled.', [], 403);
        }

        $challenge = $this->secondFactor->challenge($user, (string) $platform['guard']);

        if ($challenge !== null) {
            return $this->ok($challenge, 'Two-factor authentication required.');
        }

        $capabilities = $this->tokens->issue($user, (string) $request->input('device_name', 'spa'));

        return $this->ok(
            $capabilities + ['user' => $this->presenter->present($user, $capabilities['abilities'])],
            'Authenticated.'
        );
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->error('UNAUTHENTICATED', 'Unauthenticated.', [], 401);
        }

        $abilities = data_get($user->currentAccessToken(), 'scopes', []);

        return $this->ok($this->presenter->present($user, is_array($abilities) ? $abilities : []), 'Identity loaded.');
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token !== null && method_exists($token, 'revoke')) {
            $token->revoke();
        }

        return $this->ok(null, 'Logged out.');
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $platform = $this->platform($request);

        if ($user === null || $platform === null) {
            return $this->error('UNAUTHENTICATED', 'Unauthenticated.', [], 401);
        }

        $name = $request->input('name');
        $email = $request->input('email');

        $errors = [];

        if ($name !== null && (! is_string($name) || trim($name) === '')) {
            $errors['name'][] = 'The name field is required.';
        }

        if ($email !== null && (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $errors['email'][] = 'The email field must be a valid email address.';
        }

        if ($errors !== []) {
            return $this->error('VALIDATION_FAILED', 'The given data was invalid.', $errors, 422);
        }

        $changingEmail = is_string($email) && $email !== $user->email;

        if ($changingEmail) {
            $provider = $this->auth->createUserProvider($platform['provider']);
            $taken = $provider?->retrieveByCredentials(['email' => $email]);

            if ($taken !== null && (string) $taken->getAuthIdentifier() !== (string) $user->getAuthIdentifier()) {
                return $this->error('VALIDATION_FAILED', 'The given data was invalid.', [
                    'email' => ['The email has already been taken.'],
                ], 422);
            }
        }

        $attributes = [];

        if (is_string($name)) {
            $attributes['name'] = trim($name);
        }

        if (is_string($email)) {
            $attributes['email'] = $email;
        }

        if ($attributes !== []) {
            $user->forceFill($attributes);
        }

        // A new address is unverified until the owner proves it.
        if ($changingEmail) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($changingEmail && ($platform['features']['verification'] ?? false)
            && method_exists($user, 'sendEmailVerificationNotification')) {
            $user->sendEmailVerificationNotification();
        }

        $abilities = data_get($user->currentAccessToken(), 'scopes', []);

        return $this->ok($this->presenter->present($user, is_array($abilities) ? $abilities : []), 'Profile updated.');
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->error('UNAUTHENTICATED', 'Unauthenticated.', [], 401);
        }

        $current = $request->input('current_password');
        $password = $request->input('password');
        $confirmation = $request->input('password_confirmation');

        $errors = [];

        if (! is_string($current) || $current === '') {
            $errors['current_password'][] = 'The current password field is required.';
        }

        if (! is_string($password) || strlen($password) < 8) {
            $errors['password'][] = 'The password must be at least 8 characters.';
        } elseif ($password !== $confirmation) {
            $errors['password_confirmation'][] = 'The password confirmation does not match.';
        }

        if ($errors !== []) {
            return $this->error('VALIDATION_FAILED', 'The given data was invalid.', $errors, 422);
        }

        if (! Hash::check((string) $current, (string) $user->getAuthPassword())) {
            return $this->error('INVALID_CURRENT_PASSWORD', 'The current password is incorrect.', [], 422);
        }

        // The model's `password` cast is `hashed`, so the plain value is hashed
        // on assignment.
        $user->forceFill(['password' => $password])->save();

        return $this->ok(null, 'Password changed.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function platform(Request $request): ?array
    {
        $guard = $request->route('guard') ?? $this->config->get('platforms.default');
        $platform = $this->config->get('platforms.platforms.'.$guard);

        return is_array($platform) ? $platform : null;
    }

    /**
     * @param array<string, mixed> $details
     */
    private function error(string $code, string $message, array $details, int $status): JsonResponse
    {
        return response()->json(
            ApiResponse::error(new ApiError($code, $message, $details, $status))->toArray(),
            $status
        );
    }

    private function ok(mixed $data, string $message): JsonResponse
    {
        return response()->json(ApiResponse::success($data, $message)->toArray());
    }
}
