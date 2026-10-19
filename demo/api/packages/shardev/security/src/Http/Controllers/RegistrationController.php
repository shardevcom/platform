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
 * First-party self-registration adapter.
 *
 * Only a platform flagged `public` accepts sign-ups: a private guard has no
 * public landing (its main page is the login), so exposing registration there
 * would contradict the guard's own policy. The account is created through the
 * platform's model (the same one its provider authenticates), so the new user
 * immediately belongs to that guard, and a token is issued exactly as login
 * would, keeping the SPA flow identical.
 */
final class RegistrationController
{
    public function __construct(
        private readonly AuthManager $auth,
        private readonly ConfigRepository $config,
        private readonly AccessTokenIssuer $tokens,
        private readonly UserPresenter $presenter,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $platform = $this->platform($request);

        if ($platform === null) {
            return $this->error('PLATFORM_NOT_FOUND', 'Unknown platform.', [], 404);
        }

        if (! ($platform['public'] ?? false)) {
            return $this->error('REGISTRATION_DISABLED', 'Registration is not open for this platform.', [], 403);
        }

        $name = $request->input('name');
        $email = $request->input('email');
        $password = $request->input('password');
        $confirmation = $request->input('password_confirmation');

        $errors = [];

        if (! is_string($name) || trim($name) === '') {
            $errors['name'][] = 'The name field is required.';
        }

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'The email field must be a valid email address.';
        }

        if (! is_string($password) || strlen($password) < 8) {
            $errors['password'][] = 'The password must be at least 8 characters.';
        } elseif ($password !== $confirmation) {
            $errors['password_confirmation'][] = 'The password confirmation does not match.';
        }

        if ($errors !== []) {
            return $this->error('VALIDATION_FAILED', 'The given data was invalid.', $errors, 422);
        }

        $provider = $this->auth->createUserProvider($platform['provider']);

        if ($provider?->retrieveByCredentials(['email' => $email]) !== null) {
            return $this->error('VALIDATION_FAILED', 'The given data was invalid.', [
                'email' => ['The email has already been taken.'],
            ], 422);
        }

        $model = (string) $platform['model'];

        if (! class_exists($model)) {
            return $this->error('REGISTRATION_UNAVAILABLE', 'This platform cannot create accounts.', [], 500);
        }

        $user = new $model();
        $user->forceFill([
            'name' => trim((string) $name),
            'email' => $email,
            // The model's `password` cast is `hashed`, so the plain value is
            // hashed on assignment.
            'password' => $password,
        ])->save();

        if (($platform['features']['verification'] ?? false) && method_exists($user, 'sendEmailVerificationNotification')) {
            $user->sendEmailVerificationNotification();
        }

        $capabilities = $this->tokens->issue($user, (string) $request->input('device_name', 'spa'));

        return response()->json(
            ApiResponse::success(
                $capabilities + ['user' => $this->presenter->present($user, $capabilities['abilities'])],
                'Registered.'
            )->toArray(),
            201
        );
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
}
