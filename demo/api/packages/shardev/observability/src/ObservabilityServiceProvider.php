<?php

declare(strict_types=1);

namespace Shardevcom\Observability;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Shardevcom\Core\Contracts\Logger as LoggerContract;
use Shardevcom\Core\Contracts\Meter as MeterContract;
use Shardevcom\Core\Contracts\Tracer as TracerContract;
use Shardevcom\Observability\Http\CorrelationMiddleware;
use Shardevcom\Observability\Logging\SecretRedactionProcessor;
use Shardevcom\Observability\Logging\StructuredLogger;
use Shardevcom\Observability\Metering\NullMeter;
use Shardevcom\Observability\Tracing\NullTracer;

/**
 * Observability integration (T-CASA-023).
 *
 * Integration only: binds the T-121 contracts to their real (or documented
 * no-backend) adapters, publishes the LifecycleHooks chain, and exposes the
 * 'correlation' middleware alias.
 *
 * The LifecycleHooks binding is composed into the ActionLifecycle by
 * ProductServiceProvider (which resolves it through the container), so module
 * providers stay unaware this package exists and the chain degrades to an
 * empty one when observability is not installed.
 */
final class ObservabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/observability.php', 'observability');

        $this->app->singleton(
            LoggerContract::class,
            fn ($app): StructuredLogger => new StructuredLogger($app->make(LoggerInterface::class))
        );

        $this->app->singleton(MeterContract::class, NullMeter::class);

        $this->app->singleton(TracerContract::class, NullTracer::class);

        $this->app->singleton(\Shardevcom\Core\Lifecycle\LifecycleHooks::class, function ($app) {
            return (new \Shardevcom\Core\Lifecycle\HookChain())
                ->with(new \Shardevcom\Observability\Lifecycle\LoggingHooks($app->make(LoggerContract::class)));
        });

        $this->app->singleton(CorrelationMiddleware::class);
    }

    public function boot(): void
    {
        $this->app->router->aliasMiddleware('correlation', CorrelationMiddleware::class);

        // Redaction processor (T-CASA-024): every app log (default stack,
        // queue/job logs included) is scrubbed for secrets.
        Log::pushProcessor(new SecretRedactionProcessor());
    }
}