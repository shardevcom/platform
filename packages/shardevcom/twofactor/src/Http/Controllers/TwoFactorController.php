<?php

declare(strict_types=1);

namespace Shardevcom\TwoFactor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Shardevcom\Core\Exceptions\BusinessRuleViolation;
use Shardevcom\Core\Results\ApiMeta;
use Shardevcom\Core\Results\ApiResponse;
use Shardevcom\TwoFactor\TwoFactorService;

/**
 * Two-Factor Authentication Controller.
 *
 * Handles TOTP-based 2FA with QR code generation and recovery codes.
 */
final class TwoFactorController
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($this->twoFactor->confirmed($user)) {
            return $this->error('Two-factor authentication is already enabled.', 400);
        }

        $setup = $this->twoFactor->createEnrollment($user);

        return response()->json(ApiResponse::success(
            $setup,
            'Two-factor authentication secret generated. Please verify to enable.'
        )->toArray(), 200);
    }

    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if ($this->twoFactor->confirmed($user)) {
            return $this->error('Two-factor authentication is already enabled.', 400);
        }

        if (! $this->twoFactor->confirmEnrollment($user, (string) $request->input('code'))) {
            return $this->error('Invalid 2FA code.', 400);
        }

        return response()->json(ApiResponse::success(
            null,
            'Two-factor authentication enabled successfully.'
        )->toArray(), 200);
    }

    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
            'code' => 'nullable|string|size:6',
            'recovery_code' => 'nullable|string',
        ]);

        $user = $request->user();

        if (! $this->twoFactor->confirmed($user)) {
            return $this->error('Two-factor authentication is not enabled.', 400);
        }

        if (! Hash::check((string) $request->input('password'), (string) $user->password)) {
            return $this->error('Invalid password.', 400);
        }

        $code = $request->input('code');
        $recovery = $request->input('recovery_code');

        if (! is_string($code) && ! is_string($recovery)) {
            return $this->error('A 2FA code or recovery code is required.', 400);
        }

        $type = is_string($recovery) && $recovery !== '' ? 'recovery_code' : 'totp';
        $value = $type === 'recovery_code' ? (string) $recovery : (string) $code;

        if (! $this->twoFactor->verifyForUser($user, $value, $type)) {
            return $this->error('Invalid 2FA code.', 400);
        }

        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        return response()->json(ApiResponse::success(
            null,
            'Two-factor authentication disabled.'
        )->toArray(), 200);
    }

    public function qrCode(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->two_factor_secret) {
            return $this->error('No 2FA secret found. Please enable 2FA first.', 400);
        }

        return response()->json(ApiResponse::success([
            'qr_code' => $this->twoFactor->qrCodeFor($user),
        ], 'QR code for authenticator app.')->toArray(), 200);
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        return response()->json(ApiResponse::success([
            'remaining' => $this->twoFactor->remainingRecoveryCodes($request->user()),
            'warning' => 'Recovery codes are stored as hashes; generate a new set to see them again.',
        ], 'Recovery code status retrieved.')->toArray(), 200);
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->twoFactor->confirmed($user)) {
            return $this->error('Two-factor authentication is not enabled.', 400);
        }

        return response()->json(ApiResponse::success([
            'recovery_codes' => $this->twoFactor->regenerateRecoveryCodes($user),
            'warning' => 'Store these codes securely. Each code can only be used once. Previous codes are now invalid.',
        ], 'Recovery codes regenerated.')->toArray(), 200);
    }

    private function error(string $message, int $status = 400): JsonResponse
    {
        $response = ApiResponse::error(
            new BusinessRuleViolation($message),
            new ApiMeta('n/a', 'n/a')
        );

        return response()->json($response->toArray(), $status);
    }
}
