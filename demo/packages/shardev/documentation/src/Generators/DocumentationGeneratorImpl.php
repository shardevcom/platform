<?php

declare(strict_types=1);

namespace Shardev\Documentation\Generators;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Shardev\Documentation\Contracts\DocumentationGenerator;
use Shardev\Documentation\DTO\EndpointDocumentation;
use Shardev\Documentation\DTO\GraphQLDocumentation;
use Shardev\Documentation\Parsers\RouteParser;
use Shardev\Documentation\Parsers\ControllerParser;
use Shardev\Documentation\Parsers\GraphQLParser;
use Shardev\Documentation\Exporters\OpenApiExporter;
use Shardev\Documentation\Exporters\GraphQLExporter;
use Shardev\Documentation\Exporters\HtmlExporter;
use Shardev\Documentation\Exporters\MarkdownExporter;

/**
 * Main documentation generator implementation.
 *
 * Boundary: orchestrates parsing, generation, and export of API documentation.
 * Uses strategy pattern for different parsers and exporters.
 */
final class DocumentationGeneratorImpl implements DocumentationGenerator
{
    public function __construct(
        private readonly RouteParser $routeParser,
        private readonly ControllerParser $controllerParser,
        private readonly GraphQLParser $graphqlParser,
        private readonly OpenApiExporter $openApiExporter,
        private readonly GraphQLExporter $graphqlExporter,
        private readonly HtmlExporter $htmlExporter,
        private readonly MarkdownExporter $markdownExporter,
        private readonly array $config,
    ) {}

    public function generateRest(): array
    {
        $routes = $this->routeParser->parse($this->config['parsing']['routes'] ?? []);
        $endpoints = $this->controllerParser->enrich($routes, $this->config['parsing']['controllers'] ?? []);

        return $this->openApiExporter->export($endpoints, $this->config['generation'] ?? []);
    }

    public function generateGraphql(): GraphQLDocumentation
    {
        $schema = $this->graphqlParser->parse($this->config['parsing']['graphql'] ?? []);

        return $this->graphqlExporter->export($schema);
    }

    public function generateCombined(): array
    {
        $rest = $this->generateRest();
        $graphql = $this->generateGraphql();

        return [
            'rest' => $rest,
            'graphql' => $graphql->toArray(),
            'generated_at' => now()->toISOString(),
            'generator' => 'shardev/documentation',
        ];
    }

    public function supportedFormats(): array
    {
        return ['openapi-json', 'openapi-yaml', 'html', 'markdown', 'graphql-sdl', 'graphql-introspection', 'postman', 'insomnia'];
    }

    public function export(string $format, string $outputPath): void
    {
        $rest = $this->generateRest();
        $graphql = $this->generateGraphql();

        match ($format) {
            'openapi-json' => $this->openApiExporter->writeJson($rest, $outputPath),
            'openapi-yaml' => $this->openApiExporter->writeYaml($rest, $outputPath),
            'html' => $this->htmlExporter->export($rest, $graphql, $outputPath),
            'markdown' => $this->markdownExporter->export($rest, $graphql, $outputPath),
            'graphql-sdl' => $this->graphqlExporter->writeSdl($graphql, $outputPath),
            'graphql-introspection' => $this->graphqlExporter->writeIntrospection($graphql, $outputPath),
            'postman' => $this->exportPostman($rest, $graphql->toArray(), $outputPath),
            'insomnia' => $this->exportInsomnia($rest, $graphql->toArray(), $outputPath),
            default => throw new \InvalidArgumentException("Unsupported format: {$format}"),
        };
    }

    private function exportPostman(array $rest, array $graphql, string $outputPath): void
    {
        $collection = [
            'info' => $rest['info'] ?? [],
            'item' => $this->convertToPostmanItems($rest['paths'] ?? []),
            'variable' => $this->extractPostmanVariables($rest),
        ];

        File::put($outputPath, json_encode($collection, JSON_PRETTY_PRINT));
    }

    private function exportInsomnia(array $rest, array $graphql, string $outputPath): void
    {
        $collection = [
            '_type' => 'export',
            '__export_format' => 4,
            '__export_date' => now()->toISOString(),
            '__export_source' => 'shardev/documentation',
            'resources' => $this->convertToInsomniaResources($rest['paths'] ?? []),
        ];

        File::put($outputPath, json_encode($collection, JSON_PRETTY_PRINT));
    }

    /**
     * @param array<string, mixed> $paths
     * @return array<int, array<string, mixed>>
     */
    private function convertToPostmanItems(array $paths): array
    {
        $items = [];

        foreach ($paths as $path => $methods) {
            foreach ($methods as $method => $operation) {
                $items[] = [
                    'name' => $operation['summary'] ?? $method.' '.$path,
                    'request' => [
                        'method' => strtoupper($method),
                        'url' => '{{baseUrl}}'.$path,
                        'header' => $this->buildPostmanHeaders($operation),
                        'body' => $this->buildPostmanBody($operation),
                    ],
                    'response' => [],
                ];
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $operation
     * @return array<int, array<string, string>>
     */
    private function buildPostmanHeaders(array $operation): array
    {
        $headers = [
            ['key' => 'Accept', 'value' => 'application/json', 'type' => 'text'],
        ];

        if (isset($operation['security'])) {
            $headers[] = ['key' => 'Authorization', 'value' => 'Bearer {{token}}', 'type' => 'text'];
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $operation
     * @return array<string, mixed>|null
     */
    private function buildPostmanBody(array $operation): ?array
    {
        if (! isset($operation['requestBody']['content']['application/json'])) {
            return null;
        }

        return [
            'mode' => 'raw',
            'raw' => json_encode($operation['requestBody']['content']['application/json']['example'] ?? new \stdClass()),
            'options' => ['raw' => ['language' => 'json']],
        ];
    }

    /**
     * @param array<string, mixed> $openapi
     * @return array<int, array<string, mixed>>
     */
    private function extractPostmanVariables(array $openapi): array
    {
        $variables = [];

        foreach (($openapi['servers'] ?? []) as $server) {
            $variables[] = [
                'key' => 'baseUrl',
                'value' => $server['url'] ?? '',
                'description' => $server['description'] ?? 'API base URL',
            ];
        }

        return $variables;
    }

    /**
     * @param array<string, mixed> $paths
     * @return array<int, array<string, mixed>>
     */
    private function convertToInsomniaResources(array $paths): array
    {
        $resources = [];

        foreach ($paths as $path => $methods) {
            foreach ($methods as $method => $operation) {
                $resources[] = [
                    '_type' => 'request',
                    'name' => $operation['summary'] ?? $method.' '.$path,
                    'method' => strtoupper($method),
                    'url' => '{{baseUrl}}'.$path,
                    'headers' => $this->buildInsomniaHeaders($operation),
                    'body' => $this->buildInsomniaBody($operation),
                ];
            }
        }

        return $resources;
    }

    /**
     * @param array<string, mixed> $operation
     * @return array<int, array<string, mixed>>
     */
    private function buildInsomniaHeaders(array $operation): array
    {
        $headers = [
            ['name' => 'Accept', 'value' => 'application/json'],
        ];

        if (isset($operation['security'])) {
            $headers[] = ['name' => 'Authorization', 'value' => 'Bearer {{token}}'];
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $operation
     * @return array<string, mixed>|null
     */
    private function buildInsomniaBody(array $operation): ?array
    {
        if (! isset($operation['requestBody']['content']['application/json'])) {
            return null;
        }

        return [
            'mimeType' => 'application/json',
            'text' => json_encode($operation['requestBody']['content']['application/json']['example'] ?? new \stdClass(), JSON_PRETTY_PRINT),
        ];
    }
}