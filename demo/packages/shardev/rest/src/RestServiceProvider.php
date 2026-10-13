<?php

declare(strict_types=1);

namespace Shardevcom\Rest;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;
use Shardevcom\Rest\Exceptions\RestExceptionHandler;
use Shardevcom\Rest\Http\Middleware\ApiVersionMiddleware;

/**
 * REST adapter integration.
 *
 * Registers versioned API routes (T-070), exception handler (T-071),
 * and API versioning middleware (T-078).
 * Controllers stay thin by rule. No business logic lives here.
 */
final class RestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ExceptionHandler::class, RestExceptionHandler::class);

        // Register API versioning middleware
        $this->app->singleton(ApiVersionMiddleware::class, function ($app) {
            return new ApiVersionMiddleware(
                defaultVersion: config('rest.version.default', '1.0'),
                deprecatedEndpoints: config('rest.deprecated', []),
                sunsetDates: config('rest.sunset', []),
            );
        });

        // Register exception handler
        $this->app->singleton(ExceptionHandler::class, RestExceptionHandler::class);
    }

    public function boot(): void
    {
        // Apply API versioning middleware to API routes.
        // aliasMiddleware(), not middleware(): Router::middleware() is routed to
        // RouteRegistrar via __call, so it silently registers nothing.
        $this->app['router']->aliasMiddleware('api.version', ApiVersionMiddleware::class);

        // Publish config
        $this->publishes([
            __DIR__.'/../config/rest.php' => config_path('rest.php'),
        ], 'rest-config');
    }
}
