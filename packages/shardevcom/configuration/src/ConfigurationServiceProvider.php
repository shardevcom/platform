<?php

declare(strict_types=1);

namespace Shardevcom\Configuration;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Shardevcom\Configuration\Providers\CoreConfigurationProvider;
use Shardevcom\Configuration\Providers\PreferencesConfigurationProvider;
use Shardevcom\Configuration\ConfigurationRegistry;

/**
 * Configuration package integration.
 *
 * Registers the ConfigurationRegistry and providers (T-080/T-081).
 * No business logic lives here.
 */
final class ConfigurationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConfigurationRegistry::class, function ($app) {
            $registry = new ConfigurationRegistry();
            $registry->register(new CoreConfigurationProvider());
            $registry->register(new PreferencesConfigurationProvider());
            return $registry;
        });
    }

    public function boot(): void
    {
        $this->loadRoutes();
    }

    /**
     * Load REST API routes for each platform.
     *
     * The configuration endpoint is public (visibility handled by ConfigurationRegistry),
     * so it does not require authentication middleware.
     */
    private function loadRoutes(): void
    {
        $platforms = Config::get('platforms.platforms', []);

        foreach ($platforms as $platform) {
            $guard = $platform['guard'];
            $middleware = array_merge(['bind.guard'], $platform['middleware'] ?? []);

            // Static prefix for backward compatibility
            Route::middleware($middleware)
                ->prefix($platform['prefix'] !== '' ? $platform['prefix'].'/api/v1' : 'api/v1')
                ->group(function () use ($guard) {
                    Route::get('configuration', \Shardevcom\Configuration\Http\Controllers\ConfigurationController::class.'@index')
                        ->name("configuration.{$guard}.index");
                });

            // Dynamic {guard} prefix for BindGuard middleware
            Route::middleware($middleware)
                ->prefix('{guard}/'.$platform['prefix'].'/api/v1')
                ->group(function () use ($guard) {
                    Route::get('configuration', \Shardevcom\Configuration\Http\Controllers\ConfigurationController::class.'@index')
                        ->name("configuration.{$guard}.index");
                });
        }
    }
}