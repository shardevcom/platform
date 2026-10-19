<?php

declare(strict_types=1);

namespace Shardevcom\Security\Http\Controllers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Shardevcom\Core\Results\ApiError;
use Shardevcom\Core\Results\ApiResponse;
use Shardevcom\Security\Authentication\AccessTokenIssuer;
use Shardevcom\Security\Authentication\UserPresenter;
use Shardevcom\Security\Infrastructure\AccountLock;
use Shardevcom\Security\Infrastructure\PlatformOtpCode;
use Shardevcom\Security\Infrastructure\UserRegistrar;
use Shardevcom\Security\Notifications\OtpNotification;

/**
 * Passwordless OTP login ({prefix}/api/v1/auth/otp/*).
 *
 * The request endpoint is deliberately opaque: it answers the same way whether
 * or not the email exists, so it cannot be used to enumerate accounts. The
 * code is bound to the platform, hashed at rest, expires, tolerates a bounded
 * number of wrong guesses and is single-use.
 */
final class OtpController
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly AccessTokenIssuer $tokens,
        private readonly UserPresenter $presenter,
        private readonly UserRegistrar $registrar,
        private readonly AccountLock $locks,
    ) {}

    public function request(Request $request): JsonResponse
    {
        $platform = $this->platform($request);

        if ($platform === null) {
            return $this->error('PLATFORM_NOT_FOUND', 'Unknown platform.', [], 404);
        }

        $email = $request->input('email');

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('VALIDATION_FAILED', 'The given data was invalid.', [
                'email' => ['The email field must be a valid email address.'],
            ], 422);
        }

        $ttl = max(1, (int) $this->config->get('security.otp.ttl_minutes', 10));

        // Only mint a code for an existing account, but always answer the same.
        if ($this->registrar->find($platform, $email) !== null) {
            $code = $this->generateCode();

            PlatformOtpCode::create([
                'guard' => $platform['guard'],
                'email' => $email,
                'code' => $this->hash($code),
                'expires_at' => now()->addMinutes($ttl),
            ]);

            Notification::route('mail', $email)->notify(new OtpNotification($code, $ttl));
        }

        return $this->ok(['sent' => true], 'If the account exists, a sign-in code has been sent.');
    }

    public function verify(Request $request): JsonResponse
    {
        $platform = $this->platform($request);

        if ($platform === null) {
            return $this->error('PLATFORM_NOT_FOUND', 'Unknown platform.', [], 404);
        }

        $email = $request->input('email');
        $code = $request->input('code');

        if (! is_string($email) || ! is_string($code) || $code === '') {
            return $this->error('VALIDATION_FAILED', 'The given data was invalid.', [
                'code' => ['The email and code fields are required.'],
            ], 422);
        }

        $record = PlatformOtpCode::query()
            ->where('guard', $platform['guard'])
            ->where('email', $email)
            ->orderByDesc('id')
            ->first();

        if ($record === null || ! $record->isPending()) {
            return $this->error('INVALID_OTP', 'This sign-in code is invalid or has expired.', [], 401);
        }

        $maxAttempts = max(1, (int) $this->config->get('security.otp.max_attempts', 5));

        if ($record->attempts >= $maxAttempts) {
            $record->forceFill(['consumed_at' => now()])->save();

            return $this->error('TOO_MANY_ATTEMPTS', 'Too many attempts for this code.', [], 429);
        }

        if (! hash_equals($record->code, $this->hash($code))) {
            $record->forceFill(['attempts' => $record->attempts + 1])->save();

            return $this->error('INVALID_OTP', 'This sign-in code is invalid or has expired.', [], 401);
        }

        $user = $this->registrar->find($platform, $email);

        if ($user === null) {
            return $this->error('INVALID_OTP', 'This sign-in code is invalid or has expired.', [], 401);
        }

        if ($this->locks->isDisabled($platform['guard'], $user->getAuthIdentifier())) {
            return $this->error('ACCOUNT_DISABLED', 'This account has been disabled.', [], 403);
        }

        $record->forceFill(['consumed_at' => now()])->save();

        $capabilities = $this->tokens->issue($user, (string) $request->input('device_name', 'spa'));

        return $this->ok(
            $capabilities + ['user' => $this->presenter->present($user, $capabilities['abilities'])],
            'Authenticated.'
        );
    }

    private function generateCode(): string
    {
        $length = max(4, min(10, (int) $this->config->get('security.otp.length', 6)));
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }

    private function hash(string $code): string
    {
        return hash('sha256', $code);
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
