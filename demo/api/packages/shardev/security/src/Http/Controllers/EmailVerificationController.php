<?php

declare(strict_types=1);

namespace Shardevcom\Security\Http\Controllers;

use Illuminate\Auth\AuthManager;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shardevcom\Core\Results\ApiError;
use Shardevcom\Core\Results\ApiResponse;

/**
 * First-party email/account verification adapter.
 *
 * The link is a Laravel signed URL scoped to the platform (`.../verify/{id}/{hash}`),
 * so `verify` is public but tamper-proof; it then compares the hash against the
 * platform user's email before marking it verified. `resend` is the
 * authenticated counterpart the SPA calls when the user asks for a new link.
 */
final class EmailVerificationController
{
    public function __construct(
        private readonly AuthManager $auth,
        private readonly ConfigRepository $config,
    ) {}

    public function verify(Request $request): JsonResponse
    {
        $platform = $this->platform($request);

        if ($platform === null) {
            return $this->error('PLATFORM_NOT_FOUND', 'Unknown platform.', [], 404);
        }

        $provider = $this->auth->createUserProvider($platform['provider']);
        $user = $provider?->retrieveById($request->route('id'));

        if ($user === null) {
            return $this->error('USER_NOT_FOUND', 'This verification link is no longer valid.', [], 404);
        }

        if (! hash_equals(sha1((string) $user->getEmailForVerification()), (string) $request->route('hash'))) {
            return $this->error('INVALID_VERIFICATION_LINK', 'This verification link is invalid.', [], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->ok(['verified' => true], 'Email already verified.');
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return $this->ok(['verified' => true], 'Email verified.');
    }

    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->error('UNAUTHENTICATED', 'Unauthenticated.', [], 401);
        }

        if (method_exists($user, 'hasVerifiedEmail') && $user->hasVerifiedEmail()) {
            return $this->ok(['verified' => true], 'Email already verified.');
        }

        if (method_exists($user, 'sendEmailVerificationNotification')) {
            $user->sendEmailVerificationNotification();
        }

        return $this->ok(['sent' => true], 'Verification link sent.');
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

    /**
     * @param array<string, mixed> $data
     */
    private function ok(array $data, string $message): JsonResponse
    {
        return response()->json(ApiResponse::success($data, $message)->toArray());
    }
}
