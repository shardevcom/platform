<?php

declare(strict_types=1);

namespace Shardevcom\TwoFactor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Shardevcom\Core\Results\ApiResponse;
use PragmaRX\Google2FALaravel\Facade as Google2FA;

/**
 * Two-Factor Authentication Controller.
 *
 * Handles TOTP-based 2FA with QR code generation and recovery codes.
 */
final class TwoFactorController
{
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->two_factor_confirmed_at) {
            return $this->error('Two-factor authentication is already enabled.', 400);
        }

        // Generate secret
        $secret = Google2FA::generateSecretKey();

        // Generate recovery codes
        $recoveryCodes = $this->generateRecoveryCodes();

        // Store secret temporarily (unconfirmed)
        $user->two_factor_secret = $secret;
        $user->two_factor_recovery_codes = json_encode($recoveryCodes);
        $user->save();

        // Generate QR code using GD backend
        $qrCode = $this->generateQrCode(
            config('twofactor.issuer', 'Shardev Platform'),
            $user->email,
            $secret
        );

        $response = ApiResponse::success([
            'secret' => $secret,
            'qr_code' => $qrCode,
            'recovery_codes' => $recoveryCodes,
            'instructions' => 'Scan the QR code with your authenticator app, then verify with /api/v1/auth/2fa/verify',
        ], 'Two-factor authentication secret generated. Please verify to enable.');

        return response()->json($response->toArray(), 200);
    }

    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if ($user->two_factor_confirmed_at) {
            return $this->error('Two-factor authentication is already enabled.', 400);
        }

        if (!$user->two_factor_secret) {
            return $this->error('No 2FA secret found. Please enable 2FA first.', 400);
        }

        $valid = Google2FA::verifyKey($user->two_factor_secret, $request->input('code'));

        if (!$valid) {
            return $this->error('Invalid 2FA code.', 400);
        }

        $user->two_factor_confirmed_at = now();
        $user->save();

        $response = ApiResponse::success(null, 'Two-factor authentication enabled successfully.');

        return response()->json($response->toArray(), 200);
    }

    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
            'code' => 'nullable|string|size:6',
        ]);

        $user = $request->user();

        if (!$user->two_factor_confirmed_at) {
            return $this->error('Two-factor authentication is not enabled.', 400);
        }

        // Verify password
        if (!Hash::check($request->input('password'), $user->password)) {
            return $this->error('Invalid password.', 400);
        }

        // If 2FA is confirmed, require 2FA code to disable
        if ($user->two_factor_confirmed_at && $request->has('code')) {
            $valid = Google2FA::verifyKey($user->two_factor_secret, $request->input('code'));

            if (!$valid) {
                return $this->error('Invalid 2FA code.', 400);
            }
        }

        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        $response = ApiResponse::success(null, 'Two-factor authentication disabled.');

        return response()->json($response->toArray(), 200);
    }

    public function qrCode(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->two_factor_secret) {
            return $this->error('No 2FA secret found. Please enable 2FA first.', 400);
        }

        $qrCode = $this->generateQrCode(
            config('twofactor.issuer', 'Shardev Platform'),
            $user->email,
            $user->two_factor_secret
        );

        $response = ApiResponse::success([
            'qr_code' => $qrCode,
        ], 'QR code for authenticator app.');

        return response()->json($response->toArray(), 200);
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->two_factor_recovery_codes) {
            return $this->error('No recovery codes found.', 404);
        }

        $codes = json_decode($user->two_factor_recovery_codes, true) ?? [];

        $response = ApiResponse::success([
            'recovery_codes' => $codes,
            'warning' => 'Store these codes securely. Each code can only be used once.',
        ], 'Recovery codes retrieved.');

        return response()->json($response->toArray(), 200);
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->two_factor_confirmed_at) {
            return $this->error('Two-factor authentication is not enabled.', 400);
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->two_factor_recovery_codes = json_encode($recoveryCodes);
        $user->save();

        $response = ApiResponse::success([
            'recovery_codes' => $recoveryCodes,
            'warning' => 'Store these codes securely. Each code can only be used once. Previous codes are now invalid.',
        ], 'Recovery codes regenerated.');

        return response()->json($response->toArray(), 200);
    }

    private function generateRecoveryCodes(): array
    {
        $count = config('twofactor.recovery_codes_count', 8);
        $length = config('twofactor.recovery_codes_length', 10);
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = Str::upper(Str::random($length));
        }

        return $codes;
    }

    private function generateQrCode(string $issuer, string $email, string $secret): string
    {
        $qrCodeUrl = Google2FA::getQRCodeUrl(
            config('twofactor.issuer', 'Shardev Platform'),
            $email,
            $secret
        );

        $renderedQrCode = (new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(300),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        ))->render(
            \BaconQrCode\Encoder\Encoder::encode($qrCodeUrl)
        );

        return 'data:image/svg+xml;base64,' . base64_encode($renderedQrCode);
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