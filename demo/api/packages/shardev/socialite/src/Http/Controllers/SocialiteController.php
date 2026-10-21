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
     * Enabled providers, for clients that render the login buttons.
     */
    public function providers(): JsonResponse
    {
        $providers = [];

        foreach ($this->socialiteRegistry->enabled() as $key => $driver) {
            $providers[] = ['key' => $key, 'name' => $driver->name()];
        }

        return response()->json([
            'status' => 'success',
            'code' => 'OK',
            'message' => '',
            'data' => ['providers' => $providers],
        ]);
    }

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
                'status' => 'success',
                'code' => 'OK',
                'message' => '',
                'data' => ['redirect_url' => $redirectUrl],
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
    public function callback(string $provider, Request $request): JsonResponse|\Illuminate\Http\RedirectResponse
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

            if (! $request->expectsJson() && ($frontend = $this->frontendRedirect($token)) !== null) {
                return redirect()->away($frontend);
            }

            // Return token and user info
            $redirectUrl = Config::get('socialite.default_redirect', '/dashboard') . "?token={$token}";

            return response()->json([
                'status' => 'success',
                'code' => 'OK',
                'message' => '',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                    'redirect_url' => $redirectUrl,
                ],
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

    /**
     * Absolute SPA URL carrying the token in the fragment, or null when the
     * frontend callback is disabled.
     */
    private function frontendRedirect(string $token): ?string
    {
        $path = (string) Config::get('socialite.frontend_redirect', '');

        if ($path === '') {
            return null;
        }

        if (! str_contains($path, '://')) {
            $base = (string) Config::get('security.frontend.url', Config::get('app.url', ''));
            $path = rtrim($base, '/').'/'.ltrim($path, '/');
        }

        $separator = str_contains($path, '#') ? '&' : '#';

        return $path.$separator.'token='.urlencode($token);
    }
}