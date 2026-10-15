<?php

declare(strict_types=1);

namespace App\Platform\Authentication;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\AdminUser;
use App\Models\EmployeeUser;

/**
 * Step-Up MFA Service (T-CASA-011).
 *
 * When a user authenticates from a new device, require additional
 * MFA verification before granting access. This prevents credential
 * stuffing and unauthorized device access.
 *
 * Device fingerprint is derived from User-Agent + IP subnet.
 * Trusted devices are cached with a configurable TTL.
 */
final class StepUpMfa
{
    public function __construct(
        private readonly array $config = null
    ) {
        $this->config ??= config('passport.step_up_mfa', []);
    }

    /**
     * Check if step-up MFA is required for this device.
     *
     * Returns true if MFA challenge is needed, false if device is trusted.
     */
    public function requiresChallenge(
        string $userId,
        string $userKind,
        string $deviceFingerprint
    ): bool {
        if (! ($this->config['enabled'] ?? true)) {
            return false;
        }

        $cacheKey = "device_trust:{$userKind}:{$userId}:{$deviceFingerprint}";

        if (Cache::has($cacheKey)) {
            return false; // Device is trusted
        }

        // Check if user has MFA enabled
        return $this->userHasMfa($userId, $userKind);
    }

    /**
     * Mark device as trusted after successful MFA challenge.
     */
    public function trustDevice(string $userId, string $userKind, string $deviceFingerprint): void
    {
        if (! ($this->config['enabled'] ?? true)) {
            return;
        }

        $cacheKey = "device_trust:{$userKind}:{$userId}:{$deviceFingerprint}";
        $ttl = $this->parseDuration($this->config['device_tracking']['trust_duration'] ?? '30 days');

        Cache::put($cacheKey, true, $ttl);
    }

    /**
     * Generate a device fingerprint from request.
     */
    public function generateFingerprint(
        string $userAgent,
        string $ip
    ): string {
        // Use first 24 bits of IP (subnet) + user agent hash
        $ipParts = explode('.', $ip);
        $subnet = implode('.', array_slice($ipParts, 0, 3)).'.0';

        return hash('sha256', $subnet.'|'.$userAgent);
    }

    /**
     * Verify TOTP code for step-up challenge.
     */
    public function verifyTotp(string $userId, string $userKind, string $code): bool
    {
        $user = $this->getUser($userId, $userKind);

        if (! $user || ! $user->two_factor_secret) {
            return false;
        }

        // Use Google2FA for TOTP verification
        $google2fa = app(\PragmaRX\Google2FA\Google2FA::class);

        return $google2fa->verifyKey($user->two_factor_secret, $code);
    }

    /**
     * Verify recovery code for step-up challenge.
     */
    public function verifyRecoveryCode(string $userId, string $userKind, string $code): bool
    {
        $user = $this->getUser($userId, $userKind);

        if (! $user || ! $user->two_factor_recovery_codes) {
            return false;
        }

        $codes = json_decode($user->two_factor_recovery_codes, true);

        if (! is_array($codes)) {
            return false;
        }

        foreach ($codes as $index => $hashedCode) {
            if (Hash::check($code, $hashedCode)) {
                // Remove used recovery code
                unset($codes[$index]);
                $user->two_factor_recovery_codes = json_encode(array_values($codes));
                $user->save();

                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has MFA enabled.
     */
    private function userHasMfa(string $userId, string $userKind): bool
    {
        $user = $this->getUser($userId, $userKind);

        return $user !== null
            && ! empty($user->two_factor_secret)
            && ! empty($user->two_factor_recovery_codes);
    }

    /**
     * Get user by ID and kind.
     */
    private function getUser(string $userId, string $userKind): ?object
    {
        return match ($userKind) {
            'AdminUser' => AdminUser::find($userId),
            'EmployeeUser' => EmployeeUser::find($userId),
            default => null,
        };
    }

    /**
     * Parse duration string to seconds.
     */
    private function parseDuration(string $duration): int
    {
        $unit = Str::last($duration);
        $value = (int) Str::beforeLast($duration, ' ');

        return match (strtolower($unit)) {
            'second', 'seconds' => $value,
            'minute', 'minutes' => $value * 60,
            'hour', 'hours' => $value * 3600,
            'day', 'days' => $value * 86400,
            'week', 'weeks' => $value * 604800,
            'month', 'months' => $value * 2592000,
            'year', 'years' => $value * 31536000,
            default => 2592000, // 30 days default
        };
    }
}