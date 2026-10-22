<?php

declare(strict_types=1);

namespace Shardevcom\TwoFactor;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Shardevcom\Core\Contracts\SecondFactorGate;
use Shardevcom\TwoFactor\Http\Controllers\TwoFactorController;
use Shardevcom\TwoFactor\Http\Controllers\TwoFactorLoginController;
use Shardevcom\TwoFactor\Http\Middleware\RequireStepUpMfa;

/**
 * Two-Factor Authentication package integration.
 *
 * Registers 2FA routes and middleware.
 */
final class TwoFactorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TwoFactorService::class);
        $this->app->singleton(SecondFactorGate::class, TwoFactorService::class);
        $this->app->singleton(\Shardevcom\TwoFactor\Http\Middleware\RequireStepUpMfa::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/twofactor.php' => config_path('twofactor.php'),
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'twofactor-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Register middleware alias
        $this->app->router->aliasMiddleware('2fa.step-up', \Shardevcom\TwoFactor\Http\Middleware\RequireStepUpMfa::class);

        $this->loadRoutes();
    }

    private function loadRoutes(): void
    {
        // Register 2FA routes under each platform
        $platforms = config('platforms.platforms', []);

        foreach ($platforms as $platform) {
            $guard = $platform['guard'];
            $prefix = $platform['prefix'] !== '' ? $platform['prefix'].'/api/v1' : 'api/v1';
            $middleware = array_merge(['bind.guard'], $platform['middleware'] ?? []);

            Route::middleware($middleware)
                ->prefix($prefix)
                ->group(function () use ($guard) {
                    Route::post('auth/2fa/challenge', [TwoFactorLoginController::class, 'challenge'])
                        ->defaults('guard', $guard)
                        ->name('auth.2fa.challenge.'.$guard);
                    Route::post('auth/2fa/setup', [TwoFactorLoginController::class, 'setup'])
                        ->defaults('guard', $guard)
                        ->name('auth.2fa.setup.'.$guard);
                    Route::post('auth/2fa/setup/confirm', [TwoFactorLoginController::class, 'confirm'])
                        ->defaults('guard', $guard)
                        ->name('auth.2fa.setup.confirm.'.$guard);
                });

            // Standard 2FA routes
            Route::middleware($middleware)
                ->prefix($prefix)
                ->group(function () use ($guard) {
                    Route::middleware('auth:'.$guard)
                        ->group(__DIR__.'/../routes/api.php');
                });

            // Dynamic {guard} prefix for BindGuard middleware
            Route::middleware($middleware)
                ->prefix('{guard}/'.$platform['prefix'].'/api/v1')
                ->group(function () use ($guard) {
                    Route::middleware('auth:'.$guard)
                        ->group(__DIR__.'/../routes/api.php');
                });
        }

        // Step-up MFA routes (no specific guard, uses auth:api)
        Route::middleware(['api', 'auth:api'])
            ->prefix('api/v1/auth/2fa/step-up')
            ->group(function () {
                Route::get('challenge', [\Shardevcom\TwoFactor\Http\Controllers\StepUpChallengeController::class, 'challenge'])
                    ->name('2fa.step-up.challenge');
                Route::post('verify', [\Shardevcom\TwoFactor\Http\Controllers\StepUpChallengeController::class, 'verify'])
                    ->name('2fa.step-up.verify');
                Route::get('devices', [\Shardevcom\TwoFactor\Http\Controllers\StepUpChallengeController::class, 'trustedDevices'])
                    ->name('2fa.step-up.devices');
                Route::delete('devices/{fingerprint}', [\Shardevcom\TwoFactor\Http\Controllers\StepUpChallengeController::class, 'revokeDevice'])
                    ->name('2fa.step-up.revoke-device');
            });
    }
}