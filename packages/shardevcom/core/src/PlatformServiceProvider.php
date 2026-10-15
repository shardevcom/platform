<?php

declare(strict_types=1);

namespace Shardevcom\Core;

use Illuminate\Support\ServiceProvider;
use Shardevcom\Core\Encryption\EncryptionServiceProvider;

/**
 * Platform bootstrapper.
 *
 * Integration-only provider: auto-registers every ServiceProvider declared
 * via `extra.platform.provider` in each package composer.json.
 * No business logic lives here.
 */
final class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register platforms config
        $this->mergeConfigFrom(
            __DIR__.'/../config/platforms.php',
            'platforms'
        );

        // Register encryption services (KMS client + blind index, T-CASA-014)
        $this->app->register(EncryptionServiceProvider::class);

        $manifest = new PackageManifest();
        $projectRoot = $this->app->basePath();

        foreach ($manifest->providers($this->app->basePath()) as $provider) {
            if ($provider === static::class || ! class_exists($provider)) {
                continue;
            }

            $this->app->register($provider);
        }
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/platforms.php' => config_path('platforms.php'),
        ], 'platform-config');

        // Load core package migrations (personal access clients, etc.)
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
