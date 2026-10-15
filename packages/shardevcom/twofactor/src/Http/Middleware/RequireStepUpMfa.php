<?php

declare(strict_types=1);

namespace Shardevcom\TwoFactor\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Shardevcom\Core\Results\ApiResponse;
use Shardevcom\Core\Results\ApiError;
use Shardevcom\Core\Exceptions\BusinessRuleViolation;
use Shardevcom\TwoFactor\StepUpMfa;

/**
 * Middleware to enforce step-up MFA for new devices.
 *
 * Checks if the current device is trusted. If not, requires MFA challenge
 * before allowing access to protected routes.
 */
final class RequireStepUpMfa
{
    public function __construct(private readonly StepUpMfa $stepUpMfa) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Skip if user doesn't have 2FA enabled
        if (!$this->userHasMfa($user)) {
            return $next($request);
        }

        // Generate device fingerprint
        $fingerprint = $this->generateFingerprint($request);

        // Check if device is trusted
        if (!$this->stepUpMfa->requiresChallenge($user->id, $user->getMorphClass(), $fingerprint)) {
            return $next($request);
        }

        // Check if step-up challenge is already satisfied for this request
        $challengeHeader = $request->header('X-2FA-Challenge');
        $challengeType = $request->header('X-2FA-Challenge-Type');

        if ($challengeHeader && $challengeType) {
            $verified = $this->verifyChallenge($user, $challengeHeader, $challengeType);

            if ($verified) {
                // Mark device as trusted
                $this->stepUpMfa->trustDevice($user->id, $user->getMorphClass(), $this->generateFingerprint($request));
                return $next($request);
            }

            // Challenge failed
            $error = ApiError::fromDomain(
                new \Shardevcom\Core\Exceptions\BusinessRuleViolation('Invalid 2FA challenge.')
            );
            return response()->json(ApiResponse::error($error, new \Shardevcom\Core\Results\ApiMeta('n/a', 'n/a'))->toArray(), 403);
        }

        // No challenge provided - return 403 with challenge required
        $error = ApiError::fromDomain(
            new BusinessRuleViolation('Step-up MFA required. Provide X-2FA-Challenge and X-2FA-Challenge-Type headers.')
        );

        $response = ApiResponse::error(
            new \Shardevcom\Core\Exceptions\Forbidden('Step-up MFA required'),
            new \Shardevcom\Core\Results\ApiMeta('n/a', 'n/a')
        );

        $response = response()->json($response->toArray(), 403);
        $response->headers->set('X-2FA-Required', 'true');
        $response->headers->set('X-2FA-Challenge-Types', 'totp,recovery_code');

        return $response;
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

    private function verifyChallenge($user, string $challenge, string $type): bool
    {
        return match ($type) {
            'totp' => $this->stepUpMfa->verifyTotp($user->id, $user->getMorphClass(), $challenge),
            'recovery_code' => $this->stepUpMfa->verifyRecoveryCode($user->id, $user->getMorphClass(), $challenge),
            default => false,
        };
    }
}