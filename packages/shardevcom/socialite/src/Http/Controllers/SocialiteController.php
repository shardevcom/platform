<?php

declare(strict_types=1);

namespace Shardevcom\Socialite\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
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
    public function redirect(string $provider, Request $request): JsonResponse
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
            $return = $request->query('return');

            if ($return !== null && (! is_string($return) || ($return !== '' && ! $this->isAllowedReturn($return)))) {
                $error = ApiError::fromDomain(new \Shardevcom\Core\Exceptions\ValidationFailed([
                    'return' => ['The return URL must be loopback or use an allowed native scheme.'],
                ]));

                return response()->json(
                    ApiResponse::error($error, new \Shardevcom\Core\Results\ApiMeta('n/a', 'n/a'))->toArray(),
                    422
                );
            }

            $state = is_string($return) && $return !== ''
                ? Crypt::encryptString($return)
                : null;
            $redirectUrl = $driver->getRedirectUrl($state);

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
            $native = $this->nativeReturn($request->query('state'));

            if ($native !== null) {
                return redirect()->away($this->appendResult($native, ['token' => $token]));
            }

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

            if ($native = $this->nativeReturn($request->query('state'))) {
                return redirect()->away($this->appendResult($native, [
                    'error' => 'No se pudo completar el acceso social.',
                ]));
            }

            $response = ApiResponse::error(
                new \Shardevcom\Core\Exceptions\BusinessRuleViolation("OAuth callback failed: {$e->getMessage()}"),
                new \Shardevcom\Core\Results\ApiMeta('n/a', 'n/a')
            );

            return response()->json($response->toArray(), 500);
        }
    }

    /**
     * Decrypt the return target carried through the provider's OAuth state.
     */
    private function nativeReturn(?string $state): ?string
    {
        if (! is_string($state) || $state === '') {
            return null;
        }

        try {
            $return = Crypt::decryptString($state);
        } catch (\Throwable) {
            return null;
        }

        return $this->isAllowedReturn($return) ? $return : null;
    }

    /**
     * Whether a client may receive the OAuth token directly: loopback HTTP(S)
     * for desktop apps or a configured custom scheme for native apps.
     */
    private function isAllowedReturn(string $return): bool
    {
        if ($return === '' || strlen($return) > 2048) {
            return false;
        }

        $parts = parse_url($return);

        if ($parts === false || empty($parts['scheme'])) {
            return false;
        }

        $scheme = strtolower((string) $parts['scheme']);

        if (in_array($scheme, ['http', 'https'], true)) {
            return in_array(strtolower((string) ($parts['host'] ?? '')), ['127.0.0.1', 'localhost', '::1', '[::1]'], true);
        }

        $schemes = array_map(
            static fn ($value): string => strtolower((string) $value),
            (array) Config::get('socialite.native_schemes', ['shardev'])
        );

        return in_array($scheme, $schemes, true) && ! empty($parts['host']);
    }

    /**
     * Append the result to the return target. Loopback URLs keep the token in
     * the query (the browser's local server never sees the fragment); custom
     * app schemes use the fragment so it stays out of server logs.
     *
     * @param array<string, string> $values
     */
    private function appendResult(string $url, array $values): string
    {
        $encoded = http_build_query($values, '', '&', PHP_QUERY_RFC3986);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (in_array($scheme, ['http', 'https'], true)) {
            $separator = str_contains($url, '?') ? '&' : '?';

            return $url.$separator.$encoded;
        }

        $separator = str_contains($url, '#') ? '&' : '#';

        return $url.$separator.$encoded;
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