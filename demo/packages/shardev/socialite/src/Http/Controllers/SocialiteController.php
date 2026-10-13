<?php

declare(strict_types=1);

namespace Shardevcom\Socialite\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Shardevcom\Core\Results\ApiError;
use Shardevcom\Core\Results\ApiResponse;
use Shardevcom\Core\Contracts\AuthenticationProvider;
use Shardevcom\Socialite\SocialiteRegistry;
use Shardevcom\Socialite\Contracts\SocialiteDriver;

/**
 * Socialite OAuth2 Controller.
 *
 * Handles OAuth2 authentication flow with social providers.
 * Uses SocialiteRegistry for driver management (allows custom drivers).
 */
final class SocialiteController
{
    public function __construct(
        private readonly AuthenticationProvider $authProvider,
        private readonly SocialiteRegistry $socialiteRegistry,
    ) {}

    /**
     * Redirect to the OAuth provider.
     */
    public function redirect(string $provider): JsonResponse
    {
        if (!$this->socialiteRegistry->has($provider)) {
            $error = ApiError::fromDomain(
                new \Shardevcom\Core\Exceptions\ResourceNotFound("Social provider [{$provider}] not registered.")
            );
            $response = ApiResponse::error($error, new \Shardevcom\Core\Results\ApiMeta('n/a', 'n/a'));

            return response()->json($response->toArray(), 404);
        }

        $driver = $this->socialiteRegistry->get($provider);

        if (!$driver->isEnabled()) {
            $error = ApiError::fromDomain(
                new \Shardevcom\Core\Exceptions\ResourceNotFound("Social provider [{$provider}] is disabled.")
            );
            $response = ApiResponse::error($error, new \Shardevcom\Core\Results\ApiMeta('n/a', 'n/a'));

            return response()->json($response->toArray(), 404);
        }

        try {
            $redirectUrl = $driver->getRedirectUrl();

            return response()->json([
                'redirect_url' => $redirectUrl,
            ]);
        } catch (\Throwable $e) {
            Log::error("Socialite redirect failed for {$provider}: " . $e->getMessage());

            $error = ApiError::fromDomain(
                new \Shardevcom\Core\Exceptions\BusinessRuleViolation("Failed to initiate OAuth flow: {$e->getMessage()}")
            );
            $response = ApiResponse::error($error, new \Shardevcom\Core\Results\ApiMeta('n/a', 'n/a'));

            return response()->json($response->toArray(), 500);
        }
    }

    /**
     * Handle the OAuth callback.
     */
    public function callback(string $provider, Request $request): JsonResponse
    {
        if (!$this->socialiteRegistry->has($provider)) {
            $error = ApiError::fromDomain(
                new \Shardevcom\Core\Exceptions\ResourceNotFound("Social provider [{$provider}] not registered.")
            );
            $response = ApiResponse::error($error, new \Shardevcom\Core\Results\ApiMeta('n/a', 'n/a'));

            return response()->json($response->toArray(), 404);
        }

        $driver = $this->socialiteRegistry->get($provider);

        if (!$driver->isEnabled()) {
            $error = ApiError::fromDomain(
                new \Shardevcom\Core\Exceptions\ResourceNotFound("Social provider [{$provider}] is disabled.")
            );
            $response = ApiResponse::error($error, new \Shardevcom\Core\Results\ApiMeta('n/a', 'n/a'));

            return response()->json($response->toArray(), 404);
        }

        try {
            $socialiteUser = $driver->handleCallback();

            // Find or create user
            $user = $driver->findOrCreateUser($socialiteUser);

            // Personal access token (Passport)
            $token = $user->createToken('socialite-' . $provider, ['*'])->accessToken;

            // Return token and user info
            $redirectUrl = Config::get('socialite.default_redirect', '/dashboard') . "?token={$token}";

            return response()->json([
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'redirect_url' => $redirectUrl,
            ]);
        } catch (\Throwable $e) {
            Log::error("Socialite callback failed for {$provider}: " . $e->getMessage());

            $response = ApiResponse::error(
                new \Shardevcom\Core\Exceptions\BusinessRuleViolation("OAuth callback failed: {$e->getMessage()}"),
                new \Shardevcom\Core\Results\ApiMeta('n/a', 'n/a')
            );

            return response()->json($response->toArray(), 500);
        }
    }
}