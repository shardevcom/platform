<?php

declare(strict_types=1);

namespace Shardevcom\Application;

use Illuminate\Support\ServiceProvider;
use Shardevcom\Application\ApplicationContext\ApplicationContextBuilderFactory;
use Shardevcom\Application\ApplicationContext\BaseApplicationContextBuilder;
use Shardevcom\Application\ApplicationContext\CliApplicationContextBuilder;
use Shardevcom\Application\ApplicationContext\GraphQLApplicationContextBuilder;
use Shardevcom\Application\ApplicationContext\HttpApplicationContextBuilder;
use Shardevcom\Application\ApplicationContext\JobApplicationContextBuilder;
use Shardevcom\Application\Contracts\ApplicationContextBuilder as ApplicationContextBuilderContract;
use Shardevcom\Core\Contracts\AuthenticationProvider;
use Shardevcom\Core\Contracts\PermissionResolver;

/**
 * Application layer integration.
 *
 * Registers ApplicationContext builders and related services.
 */
final class ApplicationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BaseApplicationContextBuilder::class, function ($app) {
            return new BaseApplicationContextBuilder(
                $app->make(PermissionResolver::class),
                config('application.context.defaults', []),
            );
        });

        $this->app->singleton(HttpApplicationContextBuilder::class, function ($app) {
            return new HttpApplicationContextBuilder(
                $app->make(AuthenticationProvider::class),
                $app->make(BaseApplicationContextBuilder::class),
            );
        });

        $this->app->singleton(CliApplicationContextBuilder::class, function ($app) {
            return new CliApplicationContextBuilder(
                $app->make(BaseApplicationContextBuilder::class),
            );
        });

        $this->app->singleton(JobApplicationContextBuilder::class, function ($app) {
            return new JobApplicationContextBuilder(
                $app->make(BaseApplicationContextBuilder::class),
            );
        });

        $this->app->singleton(GraphQLApplicationContextBuilder::class, function ($app) {
            return new GraphQLApplicationContextBuilder(
                $app->make(BaseApplicationContextBuilder::class),
            );
        });

        $this->app->singleton(ApplicationContextBuilderFactory::class, function ($app) {
            return new ApplicationContextBuilderFactory(
                $app->make(BaseApplicationContextBuilder::class),
                $app->make(HttpApplicationContextBuilder::class),
                $app->make(CliApplicationContextBuilder::class),
                $app->make(JobApplicationContextBuilder::class),
                $app->make(GraphQLApplicationContextBuilder::class),
            );
        });

        // Alias for the contract
        $this->app->alias(ApplicationContextBuilderFactory::class, ApplicationContextBuilderContract::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/application.php' => config_path('application.php'),
        ], 'application-config');
    }
}