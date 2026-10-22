<?php

declare(strict_types=1);

namespace Shardevcom\TwoFactor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shardevcom\Core\Exceptions\BusinessRuleViolation;
use Shardevcom\Core\Results\ApiError;
use Shardevcom\Core\Results\ApiMeta;
use Shardevcom\Core\Results\ApiResponse;
use Shardevcom\TwoFactor\TwoFactorService;

/**
 * Public second-factor steps that complete a password login.
 *
 * These endpoints accept only the short-lived token issued by the login
 * adapter, never a Passport token: they are how a privileged user obtains one.
 */
final class TwoFactorLoginController
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function challenge(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_token' => 'required|string',
            'code' => 'required|string',
            'type' => 'nullable|in:totp,recovery_code',
            'device_name' => 'nullable|string|max:100',
        ]);

        try {
            $result = $this->twoFactor->verifyChallenge(
                (string) $request->input('challenge_token'),
                (string) $request->input('code'),
                (string) ($request->input('type') ?? 'totp'),
                (string) ($request->input('device_name') ?? 'spa'),
            );
        } catch (BusinessRuleViolation $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return response()->json(ApiResponse::success($result, 'Authenticated.')->toArray(), 200);
    }

    public function setup(Request $request): JsonResponse
    {
        $request->validate([
            'enrollment_token' => 'required|string',
        ]);

        try {
            $setup = $this->twoFactor->setup((string) $request->input('enrollment_token'));
        } catch (BusinessRuleViolation $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return response()->json(ApiResponse::success($setup, 'Two-factor enrollment started.')->toArray(), 200);
    }

    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'enrollment_token' => 'required|string',
            'code' => 'required|string|size:6',
            'device_name' => 'nullable|string|max:100',
        ]);

        try {
            $result = $this->twoFactor->confirmSetup(
                (string) $request->input('enrollment_token'),
                (string) $request->input('code'),
                (string) ($request->input('device_name') ?? 'spa'),
            );
        } catch (BusinessRuleViolation $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return response()->json(ApiResponse::success($result, 'Two-factor authentication enabled.')->toArray(), 200);
    }

    private function error(string $message, int $status): JsonResponse
    {
        $error = new ApiError('TWO_FACTOR_FAILED', $message, [], $status);

        return response()->json(ApiResponse::error($error, new ApiMeta('n/a', 'n/a'))->toArray(), $status);
    }
}
