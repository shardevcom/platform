<?php

declare(strict_types=1);

namespace Shardevcom\Persistence;

use Illuminate\Support\ServiceProvider;

/**
 * Persistence package integration.
 *
 * Registers query executor / persistence adapters (T-041/T-052).
 * No business logic lives here.
 */
final class PersistenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
