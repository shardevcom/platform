<?php

declare(strict_types=1);

namespace Shardevcom\Resources;

use Illuminate\Support\ServiceProvider;
use Shardevcom\Core\Contracts\ResourceRegistry as RegistryContract;

/**
 * Resources package integration.
 *
 * Registers resource definitions/registry bindings (T-020/T-021).
 * No business logic lives here.
 */
final class ResourcesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RegistryContract::class, InMemoryResourceRegistry::class);
    }

    public function boot(): void
    {
        //
    }
}
