<?php

declare(strict_types=1);

namespace Shardevcom\TwoFactor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Shardevcom\Core\Results\ApiResponse;
use Shardevcom\Core\Results\ApiError;
use Shardevcom\Core\Exceptions\BusinessRuleViolation;
use Shardevcom\TwoFactor\StepUpMfa;

/**
 * Controller for step-up MFA challenges.
 *
 * Provides endpoints for initiating and verifying step-up MFA challenges
 * when a user accesses the system from a new device.
 */
final class StepUpChallengeController
{
    public function __construct(private readonly StepUpMfa $stepUpMfa) {}

    /**
     * Initiate a step-up MFA challenge.
     *
     * Returns the available challenge types for the authenticated user.
     */
    public function challenge(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->error('Unauthenticated.', 401);
        }

        if (!$this->userHasMfa($request->user())) {
            return $this->error('User does not have 2FA enabled.', 400);
        }

        $challenges = [];

        // TOTP challenge
        $challenges[] = [
            'type' => 'totp',
            'label' => 'Authenticator App (TOTP)',
            'description' => 'Enter the 6-digit code from your authenticator app.',
        ];

        // Recovery code challenge
        $challenges[] = [
            'type' => 'recovery_code',
            'label' => 'Recovery Code',
            'description' => 'Enter one of your recovery codes.',
        ];

        $response = ApiResponse::success([
            'challenges' => $challenges,
            'message' => 'Step-up MFA required. Complete one of the challenges below.',
        ], 'Step-up MFA required.');

        return response()->json($response->toArray(), 403);
    }

    /**
     * Verify a step-up MFA challenge.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'challenge' => 'required|string',
            'type' => 'required|in:totp,recovery_code',
        ]);

        $user = $request->user();

        if (!$user) {
            return $this->error('Unauthenticated.', 401);
        }

        if (!$this->userHasMfa($request->user())) {
            return $this->error('User does not have 2FA enabled.', 400);
        }

        $verified = false;
        $challenge = $request->input('challenge');
        $type = $request->input('type');

        if ($type === 'totp') {
            $verified = $this->verifyTotp($request->user(), $challenge);
        } elseif ($type === 'recovery_code') {
            $verified = $this->verifyRecoveryCode($request->user(), $challenge);
        }

        if (!$verified) {
            return $this->error('Invalid challenge.', 403);
        }

        // Generate device fingerprint for trust
        $fingerprint = $this->generateFingerprint($request);

        // Mark device as trusted
        $this->stepUpMfa->trustDevice($user->id, $user->getMorphClass(), $this->generateFingerprint($request));

        $response = ApiResponse::success([
            'trusted' => true,
            'message' => 'Device marked as trusted.',
        ], 'Challenge verified successfully.');

        return response()->json($response->toArray(), 200);
    }

    /**
     * Get trusted devices for the current user.
     */
    public function trustedDevices(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->error('Unauthenticated.', 401);
        }

        // This would require a trusted_devices table or similar
        // For now, return empty array as placeholder
        $response = ApiResponse::success([
            'devices' => [],
            'message' => 'Trusted device management not yet implemented.',
        ], 'Trusted devices retrieved.');

        return response()->json($response->toArray(), 200);
    }

    /**
     * Revoke trust for a device.
     */
    public function revokeDevice(Request $request): JsonResponse
    {
        $request->validate([
            'fingerprint' => 'required|string',
        ]);

        $user = $request->user();

        if (!$user) {
            return $this->error('Unauthenticated.', 401);
        }

        // This would require a trusted_devices table
        // For now, return not implemented
        return $this->error('Not implemented.', 501);
    }

    private function userHasMfa($user): bool
    {
        return !empty($user->two_factor_secret)
            && !empty($user->two_factor_recovery_codes)
            && $user->two_factor_confirmed_at !== null;
    }

    private function generateFingerprint(Request $request): string
    {
        $userAgent = $request->userAgent() ?? '';
        $ip = $request->ip() ?? '';

        // Use first 24 bits of IP (subnet) + user agent hash
        $ipParts = explode('.', $ip);
        $subnet = implode('.', array_slice($ipParts, 0, 3)) . '.0';

        return hash('sha256', $subnet . '|' . $userAgent);
    }

    private function verifyTotp($user, string $code): bool
    {
        if (!$user->two_factor_secret) {
            return false;
        }

        $google2fa = app(\PragmaRX\Google2FALaravel\Facade::class);
        return $google2fa->verifyKey($user->two_factor_secret, $code);
    }

    private function verifyRecoveryCode(string $userId, string $code): bool
    {
        $user = \App\Models\AdminUser::find($userId)
            ?? \App\Models\EmployeeUser::find($userId)
            ?? \App\Models\User::find($userId);

        if (!$user || !$user->two_factor_recovery_codes) {
            return false;
        }

        $codes = json_decode($user->two_factor_recovery_codes, true);

        if (!is_array($codes)) {
            return false;
        }

        foreach ($codes as $index => $hashedCode) {
            if (\Illuminate\Support\Facades\Hash::check($code, $hashedCode)) {
                // Remove used recovery code
                unset($codes[$index]);
                $user->two_factor_recovery_codes = json_encode(array_values($codes));
                $user->save();

                return true;
            }
        }

        return false;
    }

    private function error(string $message, int $status = 400): JsonResponse
    {
        $response = ApiResponse::error(
            new \Shardevcom\Core\Exceptions\BusinessRuleViolation($message),
            new \Shardevcom\Core\Results\ApiMeta('n/a', 'n/a')
        );

        return response()->json($response->toArray(), $status);
    }
}