<?php

declare(strict_types=1);

namespace Shardevcom\Security;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Shardevcom\Security\Authentication\AuthBootstrapServiceProvider;
use Shardevcom\Security\Authentication\ScopeCatalogue;
use Shardevcom\Security\Http\Controllers\AuthController;
use Shardevcom\Security\Http\Middleware\BindGuard;
use Shardevcom\Security\Http\Middleware\CrossTenantIdorMiddleware;
use Shardevcom\Security\Http\Middleware\CspMiddleware;
use Shardevcom\Security\Http\Middleware\RateLimitMiddleware;
use Shardevcom\Security\Http\Middleware\SecurityHeadersMiddleware;

/**
 * Security package integration.
 *
 * Registers security middleware for cross-tenant IDOR protection, CSP headers
 * and rate limiting.
 */
final class SecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/security.php', 'security');

        // Multi-platform auth wiring (guards, providers, password resets).
        // Eloquent user providers are an infrastructure concern: they live in
        // this adapter, not in core (T-090/T-CASA-012).
        $this->app->register(AuthBootstrapServiceProvider::class);

        // Scope vocabulary shared by Passport::tokensCan() (app) and the login
        // adapter's createToken() (issuance).
        $this->app->singleton(ScopeCatalogue::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/security.php' => config_path('security.php'),
        ], 'security-config');

        // Register middleware aliases. Router::aliasMiddleware is the only
        // registry the pipeline reads during route resolution;
        // Route::middleware('name', Class) here would return a pending
        // registration and silently register nothing.
        $router = $this->app['router'];
        $router->aliasMiddleware('bind.guard', BindGuard::class);
        $router->aliasMiddleware('cross-tenant-idor', CrossTenantIdorMiddleware::class);
        $router->aliasMiddleware('csp', CspMiddleware::class);
        $router->aliasMiddleware('rate.limit', RateLimitMiddleware::class);
        $router->aliasMiddleware('security.headers', SecurityHeadersMiddleware::class);

        // Auto-apply CSP to all API routes
        Route::middleware('csp')->group(function () {
            // CSP is applied globally via middleware registration in kernel
        });

        $this->loadAuthRoutes();
    }

    /**
     * Register the first-party token login surface once per platform.
     *
     * `auth/login` is public (it is the credential exchange); `auth/me` and
     * `auth/logout` require the platform guard. The `{guard}` route default
     * lets BindGuard resolve the platform and the controller authenticate
     * against the right provider without duplicating the controller.
     */
    private function loadAuthRoutes(): void
    {
        $platforms = (array) config('platforms.platforms', []);

        foreach ($platforms as $platform) {
            $guard = $platform['guard'];
            // The authenticated surface must use the platform's API guard when
            // it has one: a session platform (web) still answers its API with
            // the sibling passport guard, and a Bearer token is not a session.
            $apiGuard = $platform['api_guard'] ?? $guard;
            $middleware = array_merge(['bind.guard'], $platform['middleware'] ?? []);
            $prefix = ($platform['prefix'] ?? '') !== ''
                ? $platform['prefix'].'/api/v1'
                : 'api/v1';

            Route::middleware($middleware)
                ->prefix($prefix)
                ->group(function () use ($guard, $apiGuard): void {
                    Route::middleware('api.version')->group(function () use ($guard, $apiGuard): void {
                        Route::post('auth/login', [AuthController::class, 'login'])
                            ->defaults('guard', $guard)
                            ->name('auth.login.'.$guard);

                        Route::middleware('auth:'.$apiGuard)->group(function () use ($guard, $apiGuard): void {
                            Route::get('auth/me', [AuthController::class, 'me'])
                                ->defaults('guard', $guard)
                                ->name('auth.me.'.$guard);

                            Route::post('auth/logout', [AuthController::class, 'logout'])
                                ->defaults('guard', $guard)
                                ->name('auth.logout.'.$guard);
                        });
                    });
                });
        }
    }
}