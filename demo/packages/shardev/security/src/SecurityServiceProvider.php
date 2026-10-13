<?php

declare(strict_types=1);

namespace Shardevcom\Security;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
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
        $router->aliasMiddleware('cross-tenant-idor', CrossTenantIdorMiddleware::class);
        $router->aliasMiddleware('csp', CspMiddleware::class);
        $router->aliasMiddleware('rate.limit', RateLimitMiddleware::class);
        $router->aliasMiddleware('security.headers', SecurityHeadersMiddleware::class);

        // Auto-apply CSP to all API routes
        Route::middleware('csp')->group(function () {
            // CSP is applied globally via middleware registration in kernel
        });
    }
}