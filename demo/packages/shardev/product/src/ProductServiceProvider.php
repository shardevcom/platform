<?php

declare(strict_types=1);

namespace Shardevcom\Product;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Shardevcom\Core\Contracts\QueryExecutor as QueryExecutorContract;
use Shardevcom\Core\Contracts\ResourceRegistry as RegistryContract;
use Shardevcom\Core\Lifecycle\ActionLifecycle;
use Shardevcom\Core\Lifecycle\HookChain;
use Shardevcom\Core\Lifecycle\LifecycleHooks;
use Shardevcom\Core\Contracts\AuthorizationManager as AuthorizationManagerContract;
use Shardevcom\Persistence\EloquentQueryExecutor;
use Shardevcom\Product\Repositories\ProductRepository;
use Shardevcom\Product\Repositories\ProductImportRepository;
use Shardevcom\Resources\ResourceDefinition;

/**
 * Product user-package integration.
 *
 * Integration only: it wires the repository, publishes the resource
 * definition into the shared registry and loads this package's migrations.
 * NO business logic lives here — that is the whole point of the example.
 */
final class ProductServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(QueryExecutorContract::class, EloquentQueryExecutor::class);

        $this->app->singleton(
            ActionLifecycle::class,
            fn ($app) => new ActionLifecycle(
                $app->make(AuthorizationManagerContract::class),
                $app->bound(LifecycleHooks::class) ? $app->make(LifecycleHooks::class) : new HookChain()
            )
        );

        $this->app->singleton(ProductRepository::class, fn ($app) => new ProductRepository(
            $app->make(ActionLifecycle::class),
            $app->make(QueryExecutorContract::class),
        ));

        $this->app->singleton(ProductImportRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->commands([
            \Shardevcom\Product\Console\Commands\PurgeProductImports::class,
            \Shardevcom\Product\Console\Commands\RecoverProductImports::class,
        ]);

        $this->app->make(RegistryContract::class)->register($this->definition());

        $this->loadRoutes();
    }

    /**
     * Load REST API routes for each platform.
     *
     * Routes are prefixed with {guard}/api/v1 where {guard} is the platform key
     * (web, admin, employee). The 'web' platform has an empty prefix.
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
                    Route::middleware('auth:'.$guard)
                        ->group(__DIR__.'/../routes/api.php');
                });

            // Dynamic {guard} prefix for BindGuard middleware
            // Note: where() constraint on group with prefix parameter has a bug in Laravel,
            // so we apply it per-route in the route file instead
            Route::middleware($middleware)
                ->prefix('{guard}/'.$platform['prefix'].'/api/v1')
                ->group(function () use ($guard) {
                    Route::middleware('auth:'.$guard)
                        ->group(__DIR__.'/../routes/api.php');
                });
        }
    }

    /**
     * Use cases need NO binding here: they are final classes whose only
     * constructor argument is the already-bound ProductRepository, so the
     * container resolves them on demand. Listing them here would only create
     * a second place to forget to update.
     */
    private function definition(): ResourceDefinition
    {
        return ProductResourceDefinition::definition();
    }
}