<?php

declare(strict_types=1);

namespace Shardevcom\Security\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Shardevcom\Core\Results\ApiError;
use Shardevcom\Core\Results\ApiResponse;

/**
 * First-party password recovery adapter (forgot / reset).
 *
 * Deliberately an adapter: it resolves the platform from the `{guard}` route
 * default the provider sets when it registers these routes per prefix, then
 * delegates the state change to Laravel's password broker (which owns the
 * token store and the reset notification). No credential or permission
 * decision lives here.
 *
 * `forgot-password` never reveals whether the email exists: it always answers
 * with the same generic message, so the endpoint cannot be used to enumerate
 * accounts. The reset link itself is delivered by email through the platform's
 * broker.
 */
final class PasswordController
{
    public function __construct(private readonly ConfigRepository $config) {}

    public function sendResetLink(Request $request): JsonResponse
    {
        $platform = $this->platform($request);

        if ($platform === null) {
            return $this->error('PLATFORM_NOT_FOUND', 'Unknown platform.', [], 404);
        }

        $email = $request->input('email');

        if (! is_string($email) || $email === '') {
            return $this->error('VALIDATION_FAILED', 'The given data was invalid.', [
                'email' => ['The email field is required.'],
            ], 422);
        }

        Password::broker($platform['password_reset'])->sendResetLink(['email' => $email]);

        return $this->ok(null, 'If the email exists, a reset link has been sent.');
    }

    public function reset(Request $request): JsonResponse
    {
        $platform = $this->platform($request);

        if ($platform === null) {
            return $this->error('PLATFORM_NOT_FOUND', 'Unknown platform.', [], 404);
        }

        $credentials = [
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            'password_confirmation' => $request->input('password_confirmation'),
            'token' => $request->input('token'),
        ];

        $errors = [];

        if (! is_string($credentials['email']) || $credentials['email'] === '') {
            $errors['email'][] = 'The email field is required.';
        }

        if (! is_string($credentials['password']) || strlen($credentials['password']) < 8) {
            $errors['password'][] = 'The password must be at least 8 characters.';
        } elseif ($credentials['password'] !== $credentials['password_confirmation']) {
            $errors['password_confirmation'][] = 'The password confirmation does not match.';
        }

        if (! is_string($credentials['token']) || $credentials['token'] === '') {
            $errors['token'][] = 'The token field is required.';
        }

        if ($errors !== []) {
            return $this->error('VALIDATION_FAILED', 'The given data was invalid.', $errors, 422);
        }

        $status = Password::broker($platform['password_reset'])->reset(
            $credentials,
            static function (object $user, string $password): void {
                // The model's `password` cast is `hashed`, so the plain value is
                // hashed on assignment; passing a hash here would double-hash.
                $user->forceFill(['password' => $password])->setRememberToken(Str::random(60));
                $user->save();

                event(new PasswordReset($user));
            }
        );

        return match ($status) {
            Password::PASSWORD_RESET => $this->ok(null, 'Password has been reset.'),
            Password::INVALID_TOKEN => $this->error('INVALID_RESET_TOKEN', 'This password reset token is invalid.', [], 422),
            Password::INVALID_USER => $this->error('INVALID_RESET_USER', 'We cannot find a user with that email address.', [], 422),
            Password::RESET_THROTTLED => $this->error('RESET_THROTTLED', 'Please wait before retrying.', [], 429),
            default => $this->error('RESET_FAILED', 'The password could not be reset.', [], 400),
        };
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
