<?php

declare(strict_types=1);

namespace Shardevcom\Documentation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Shardevcom\Documentation\Contracts\DocumentationGenerator;

/**
 * Generate API documentation command.
 */
final class GenerateDocumentationCommand extends Command
{
    protected $signature = 'docs:generate
                            {--format=* : Output formats (openapi-json, openapi-yaml, html, markdown, graphql-sdl, graphql-introspection, postman, insomnia)}
                            {--output= : Output directory (default: public/docs)}
                            {--serve : Serve the generated documentation}';

    protected $description = 'Generate API documentation from routes, controllers, and GraphQL schema';

    public function handle(DocumentationGenerator $generator): int
    {
        $formats = $this->option('format') ?: $generator->supportedFormats();
        $outputDir = $this->option('output') ?: config('documentation.generation.output_dir', 'public/docs');

        $this->info("Generating documentation to: {$outputDir}");

        if (! File::isDirectory($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        $bar = $this->output->createProgressBar(count($formats));
        $bar->start();

        foreach ($formats as $format) {
            $outputFile = $this->resolveOutputFile($outputDir, $format);

            try {
                $generator->export($format, $outputFile);
                $this->line("  <info>✓</info> {$format} -> {$outputFile}");
            } catch (\Throwable $e) {
                $this->line("  <error>✗</error> {$format}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // Also generate combined documentation
        $combined = $generator->generateCombined();
        $combinedFile = $outputDir.'/documentation.json';
        File::put($combinedFile, json_encode($combined, JSON_PRETTY_PRINT));
        $this->line("  <info>✓</info> combined -> {$combinedFile}");

        if ($this->option('serve')) {
            $this->serveDocumentation($outputDir);
        }

        $this->info('Documentation generation completed!');

        return self::SUCCESS;
    }

    private function resolveOutputFile(string $outputDir, string $format): string
    {
        return match ($format) {
            'openapi-json' => $outputDir.'/openapi.json',
            'openapi-yaml' => $outputDir.'/openapi.yaml',
            'html' => $outputDir.'/index.html',
            'markdown' => $outputDir.'/README.md',
            'graphql-sdl' => $outputDir.'/graphql/schema.graphql',
            'graphql-introspection' => $outputDir.'/graphql/introspection.json',
            'postman' => $outputDir.'/postman-collection.json',
            'insomnia' => $outputDir.'/insomnia-collection.json',
            default => $outputDir."/{$format}.json",
        };
    }

    private function serveDocumentation(string $outputDir): void
    {
        $this->warn('Starting documentation server...');
        $this->warn('Open http://localhost:8080 in your browser');
        $this->warn('Press Ctrl+C to stop');

        // This would require a proper HTTP server
        // For now, just show the command to run
        $this->line('Run: php -S localhost:8080 -t '.$outputDir);
    }
}