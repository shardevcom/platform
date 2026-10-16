<?php

declare(strict_types=1);

namespace Shardevcom\Widgets;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Shardevcom\Widgets\WidgetsRegistry;
use Shardevcom\Widgets\Widgets\CardWidgetProvider;
use Shardevcom\Widgets\Widgets\ChartWidgetProvider;
use Shardevcom\Widgets\Widgets\MetricWidgetProvider;
use Shardevcom\Widgets\Widgets\TableWidgetProvider;

/**
 * Widgets package integration.
 *
 * Registers the WidgetsRegistry and widget providers.
 */
final class WidgetsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WidgetsRegistry::class, function ($app) {
            $registry = new WidgetsRegistry();
            $registry->register(new CardWidgetProvider());
            $registry->register(new ChartWidgetProvider());
            $registry->register(new MetricWidgetProvider());
            $registry->register(new TableWidgetProvider());
            return $registry;
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/widgets.php' => config_path('widgets.php'),
        ], 'widgets-config');

        $this->loadRoutes();
    }

    private function loadRoutes(): void
    {
        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/api.php');
    }
}