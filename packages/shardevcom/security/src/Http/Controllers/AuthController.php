<?php

declare(strict_types=1);

namespace Shardevcom\Security\Http\Controllers;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shardevcom\Core\Results\ApiError;
use Shardevcom\Core\Results\ApiResponse;
use Shardevcom\Security\Authentication\AccessTokenIssuer;
use Shardevcom\Security\Authentication\UserPresenter;

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
