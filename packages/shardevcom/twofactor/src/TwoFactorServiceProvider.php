<?php

declare(strict_types=1);

namespace Shardevcom\TwoFactor;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Shardevcom\TwoFactor\Http\Controllers\TwoFactorController;

/**
 * Two-Factor Authentication package integration.
 *
 * Registers 2FA routes and middleware.
 */
final class TwoFactorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/twofactor.php' => config_path('twofactor.php'),
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'twofactor-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadRoutes();
    }

    private function loadRoutes(): void
    {
        // These routes sit under the un-prefixed /api/v1 surface, which belongs
        // to the web platform. That platform's guard is session-based, so a
        // token-bearing request authenticates against its api_guard instead
        // (see config/platforms.php).
        Route::middleware(['api', 'auth:api'])
            ->prefix('api/v1/auth/2fa')
            ->group(__DIR__.'/../routes/api.php');
    }
}