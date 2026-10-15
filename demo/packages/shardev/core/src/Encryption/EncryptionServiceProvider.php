<?php

declare(strict_types=1);

namespace Shardevcom\Core\Encryption;

use Illuminate\Support\ServiceProvider;
use Shardevcom\Core\Contracts\BlindIndex;
use Shardevcom\Core\Contracts\KmsClient;

/**
 * Encryption service provider.
 *
 * Registers KMS clients and blind index services.
 */
final class EncryptionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/encryption.php', 'encryption');

        // Register KMS client as singleton
        $this->app->singleton(\Shardevcom\Core\Contracts\KmsClient::class, function ($app) {
            return \Shardevcom\Core\Encryption\KmsClientFactory::getDefault();
        });

        // Register blind index service
        $this->app->singleton(\Shardevcom\Core\Contracts\BlindIndex::class, function ($app) {
            return new \Shardevcom\Core\Encryption\BlindIndexService(
                $app->make(\Shardevcom\Core\Contracts\KmsClient::class)
            );
        });

        // Register factory
        $this->app->singleton(\Shardevcom\Core\Encryption\KmsClientFactory::class, function ($app) {
            return new \Shardevcom\Core\Encryption\KmsClientFactory();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/encryption.php' => config_path('encryption.php'),
        ], 'encryption-config');
    }
}