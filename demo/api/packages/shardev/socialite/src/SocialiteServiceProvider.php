<?php

declare(strict_types=1);

namespace Shardevcom\Socialite;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Shardevcom\Socialite\Providers\GoogleDriver;
use Shardevcom\Socialite\Providers\MicrosoftDriver;
use Shardevcom\Socialite\Contracts\SocialiteDriver;
use Shardevcom\Socialite\SocialiteRegistry;

/**
 * Socialite package integration.
 *
 * Registers OAuth2 authentication routes and providers.
 * Default drivers (Google, Microsoft) can be overridden by user configuration.
 */
final class SocialiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/socialite.php', 'socialite');

        $this->app->singleton(SocialiteRegistry::class, function ($app) {
            $registry = new SocialiteRegistry();

            // Register default drivers (can be overridden by user config)
            $registry->register(new GoogleDriver());
            $registry->register(new MicrosoftDriver());

            return $registry;
        });

        // Allow user to override drivers via config
        $this->app->resolving(SocialiteRegistry::class, function (SocialiteRegistry $registry, $app) {
            $config = $app['config']->get('socialite.providers', []);

            foreach ($config as $key => $config) {
                if (!isset($config['enabled']) || !$config['enabled']) {
                    continue;
                }

                // Allow custom driver class via config
                if (isset($config['driver_class']) && class_exists($config['driver_class'])) {
                    $driver = new $config['driver_class']();
                    $registry->register($driver);
                }
            }
        });
    }

    public function boot(): void
    {
        $this->bridgeProviderConfig();

        $this->publishes([
            __DIR__.'/../config/socialite.php' => config_path('socialite.php'),
        ], 'socialite-config');

        $this->loadRoutes();
    }

    /**
     * Laravel Socialite reads `services.{provider}`, while this package keeps
     * the providers under `socialite.providers`. Mirror the credentials so a
     * single config block is enough.
     */
    private function bridgeProviderConfig(): void
    {
        foreach ((array) config('socialite.providers', []) as $key => $provider) {
            if (! is_array($provider) || empty($provider['client_id'])) {
                continue;
            }

            if (! empty(config("services.{$key}.client_id"))) {
                continue;
            }

            $redirect = $provider['redirect'] ?? null;

            if (is_string($redirect) && $redirect !== '' && ! str_contains($redirect, '://')) {
                $redirect = rtrim((string) config('app.url'), '/').'/'.ltrim($redirect, '/');
            }

            config(["services.{$key}" => [
                'client_id' => $provider['client_id'],
                'client_secret' => $provider['client_secret'] ?? null,
                'redirect' => $redirect,
            ]]);
        }
    }

    private function loadRoutes(): void
    {
        Route::middleware('api')
            ->prefix('api/v1/auth/social')
            ->group(__DIR__.'/../routes/api.php');
    }
}