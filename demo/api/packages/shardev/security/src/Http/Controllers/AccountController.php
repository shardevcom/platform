<?php

declare(strict_types=1);

namespace Shardevcom\Security\Http\Controllers;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Shardevcom\Core\Results\ApiError;
use Shardevcom\Core\Results\ApiResponse;
use Shardevcom\Security\Infrastructure\AccountLock;

/**
 * Account disable/reactivate ({prefix}/api/v1/auth/account/*).
 *
 * A user disables their own account (confirmed with the current password) and
 * reactivates it with their credentials. Both flows only ever touch the actor's
 * own lock row, so there is no cross-account surface here.
 */
final class AccountController
{
    public function __construct(
        private readonly AuthManager $auth,
        private readonly ConfigRepository $config,
        private readonly AccountLock $locks,
    ) {}

    public function disable(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->error('UNAUTHENTICATED', 'Unauthenticated.', [], 401);
        }

        $password = $request->input('current_password');

        if (! is_string($password) || $password === '') {
            return $this->error('VALIDATION_FAILED', 'The given data was invalid.', [
                'current_password' => ['The current password field is required.'],
            ], 422);
        }

        if (! Hash::check($password, (string) $user->getAuthPassword())) {
            return $this->error('INVALID_CURRENT_PASSWORD', 'The current password is incorrect.', [], 422);
        }

        $reason = $request->input('reason');

        $this->locks->disable(
            $this->guard($request),
            $user->getAuthIdentifier(),
            is_string($reason) ? $reason : null,
        );

        // Drop every token so a stolen one cannot outlive the disable.
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        return $this->ok(['disabled' => true], 'Account disabled.');
    }

    public function reactivate(Request $request): JsonResponse
    {
        $platform = $this->platform($request);

        if ($platform === null) {
            return $this->error('PLATFORM_NOT_FOUND', 'Unknown platform.', [], 404);
        }

        $email = $request->input('email');
        $password = $request->input('password');

        $errors = [];

        if (! is_string($email) || $email === '') {
            $errors['email'][] = 'The email field is required.';
        }

        if (! is_string($password) || $password === '') {
            $errors['password'][] = 'The password field is required.';
        }

        if ($errors !== []) {
            return $this->error('VALIDATION_FAILED', 'The given data was invalid.', $errors, 422);
        }

        $provider = $this->auth->createUserProvider($platform['provider']);
        $user = $provider?->retrieveByCredentials(['email' => $email]);

        if ($user === null || ! $provider->validateCredentials($user, ['password' => $password])) {
            return $this->error('INVALID_CREDENTIALS', 'These credentials do not match our records.', [], 401);
        }

        $this->locks->enable($this->guard($request), $user->getAuthIdentifier());

        return $this->ok(['disabled' => false], 'Account reactivated.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function platform(Request $request): ?array
    {
        $platform = $this->config->get('platforms.platforms.'.$this->guard($request));

        return is_array($platform) ? $platform : null;
    }

    private function guard(Request $request): string
    {
        return (string) ($request->route('guard') ?? $this->config->get('platforms.default', 'web'));
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
