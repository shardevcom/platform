<?php

declare(strict_types=1);

namespace Shardev\Documentation;

use Illuminate\Support\ServiceProvider;
use Shardev\Documentation\Contracts\DocumentationGenerator;
use Shardev\Documentation\Generators\DocumentationGeneratorImpl;
use Shardev\Documentation\Parsers\ControllerParser;
use Shardev\Documentation\Parsers\GraphQLParser;
use Shardev\Documentation\Parsers\RouteParser;
use Shardev\Documentation\Exporters\OpenApiExporter;
use Shardev\Documentation\Exporters\GraphQLExporter;
use Shardev\Documentation\Exporters\HtmlExporter;
use Shardev\Documentation\Exporters\MarkdownExporter;

/**
 * Documentation package service provider.
 */
final class DocumentationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/documentation.php', 'documentation');

        // Parser bindings
        $this->app->singleton(RouteParser::class);
        $this->app->singleton(ControllerParser::class);
        $this->app->singleton(GraphQLParser::class);

        // Exporter bindings
        $this->app->singleton(OpenApiExporter::class);
        $this->app->singleton(GraphQLExporter::class);
        $this->app->singleton(HtmlExporter::class);
        $this->app->singleton(MarkdownExporter::class);

        // Generator binding
        $this->app->singleton(DocumentationGenerator::class, function ($app) {
            return new DocumentationGeneratorImpl(
                routeParser: $app->make(RouteParser::class),
                controllerParser: $app->make(ControllerParser::class),
                graphqlParser: $app->make(GraphQLParser::class),
                openApiExporter: $app->make(OpenApiExporter::class),
                graphqlExporter: $app->make(GraphQLExporter::class),
                htmlExporter: $app->make(HtmlExporter::class),
                markdownExporter: $app->make(MarkdownExporter::class),
                config: $app->config['documentation'] ?? [],
            );
        });

        // Console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\GenerateDocumentationCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/documentation.php' => config_path('documentation.php'),
        ], 'documentation-config');

        // Register routes for documentation UI
        if (($this->app['config']['documentation.ui.enabled'] ?? true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }
    }
}