<?php

declare(strict_types=1);

namespace Shardevcom\Authorization;

use Illuminate\Support\ServiceProvider;
use Shardevcom\Core\Contracts\AuthorizationManager as ManagerContract;
use Shardevcom\Core\Contracts\PermissionResolver as ResolverContract;

/**
 * Authorization package integration.
 *
 * Registers the AuthorizationManager / PermissionResolver bindings (T-030/T-031).
 * No business logic lives here.
 */
final class AuthorizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Only bind PermissionResolver if not already bound (allows app to provide its own)
        if (! $this->app->bound(ResolverContract::class)) {
            $this->app->singleton(ResolverContract::class, fn () => new InMemoryPermissionResolver());
        }

        $this->app->singleton(
            ManagerContract::class,
            fn ($app) => new AuthorizationManager($app->make(ResolverContract::class))
        );
    }

    public function boot(): void
    {
        //
    }
}
