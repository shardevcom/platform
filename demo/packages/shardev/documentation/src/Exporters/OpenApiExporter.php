<?php

declare(strict_types=1);

namespace Shardev\Documentation\Exporters;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/**
 * Exports OpenAPI 3.1 specification.
 *
 * Boundary: formats endpoint documentation as OpenAPI 3.1 JSON/YAML.
 */
final class OpenApiExporter
{
    public function export(array $endpoints, array $config): array
    {
        $paths = $this->buildPaths($endpoints);
        $components = $this->buildComponents($endpoints, $config);
        $security = $config['security'] ?? [];

        return [
            'openapi' => '3.1.0',
            'info' => $config['info'] ?? [
                'title' => 'API Documentation',
                'version' => '1.0.0',
            ],
            'servers' => $config['servers'] ?? [
                ['url' => '/', 'description' => 'Default server'],
            ],
            'paths' => $paths,
            'components' => $components,
            'security' => $this->buildSecurityRequirements($security),
            'tags' => $config['tags'] ?? [],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $endpoints
     * @return array<string, array<string, mixed>>
     */
    private function buildPaths(array $endpoints): array
    {
        $paths = [];

        foreach ($endpoints as $endpoint) {
            $path = $endpoint['path'];
            $method = strtolower($endpoint['method']);

            if (! isset($paths[$path])) {
                $paths[$path] = [];
            }

            $operation = [
                'operationId' => $endpoint['operationId'],
                'summary' => $endpoint['summary'],
                'description' => $endpoint['description'],
                'tags' => $endpoint['tags'],
                'parameters' => $endpoint['parameters'],
                'responses' => $this->formatResponses($endpoint['responses']),
                'deprecated' => $endpoint['deprecated'],
            ];

            if ($endpoint['requestBody']) {
                $operation['requestBody'] = $endpoint['requestBody'];
            }

            if (! empty($endpoint['security'])) {
                $operation['security'] = $endpoint['security'];
            }

            if (! empty($endpoint['extensions'])) {
                foreach ($endpoint['extensions'] as $key => $value) {
                    $operation[$key] = $value;
                }
            }

            $paths[$path][$method] = $operation;
        }

        return $paths;
    }

    /**
     * @param array<int, array<string, mixed>> $endpoints
     * @return array<string, mixed>
     */
    private function buildComponents(array $endpoints, array $config): array
    {
        $schemas = [];
        $securitySchemes = [];

        // Extract schemas from request/response bodies
        foreach ($endpoints as $endpoint) {
            $this->extractSchemas($endpoint, $schemas);
        }

        // Add security schemes from config
        foreach (($config['security'] ?? []) as $name => $scheme) {
            $securitySchemes[$name] = $scheme;
        }

        // Add common error schema
        $schemas['Error'] = [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'example' => 'error'],
                'code' => ['type' => 'string', 'example' => 'VALIDATION_FAILED'],
                'message' => ['type' => 'string', 'example' => 'Validation failed'],
                'data' => ['type' => 'null'],
                'meta' => [
                    'type' => 'object',
                    'properties' => [
                        'request_id' => ['type' => 'string'],
                        'correlation_id' => ['type' => 'string'],
                        'details' => ['type' => 'object'],
                    ],
                ],
            ],
        ];

        // Pagination schema
        $schemas['Pagination'] = [
            'type' => 'object',
            'properties' => [
                'page' => ['type' => 'integer', 'example' => 1],
                'per_page' => ['type' => 'integer', 'example' => 15],
                'total' => ['type' => 'integer', 'example' => 100],
                'last_page' => ['type' => 'integer', 'example' => 7],
            ],
        ];

        // Success response envelope
        $schemas['SuccessResponse'] = [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['success'], 'example' => 'success'],
                'code' => ['type' => 'string', 'example' => 'OK'],
                'message' => ['type' => 'string', 'example' => 'Operation successful'],
                'data' => ['type' => 'object'],
                'meta' => [
                    'type' => 'object',
                    'properties' => [
                        'pagination' => ['$ref' => '#/components/schemas/Pagination'],
                        'request_id' => ['type' => 'string'],
                        'correlation_id' => ['type' => 'string'],
                    ],
                ],
            ],
        ];

        return [
            'schemas' => $schemas,
            'securitySchemes' => $securitySchemes,
        ];
    }

    /**
     * @param array<string, mixed> $endpoint
     * @param array<string, mixed> &$schemas
     */
    private function extractSchemas(array $endpoint, array &$schemas): void
    {
        // Extract from requestBody
        if (isset($endpoint['requestBody']['content']['application/json']['schema'])) {
            $this->inlineSchema($endpoint['requestBody']['content']['application/json']['schema'], $schemas);
        }

        // Extract from responses
        foreach ($endpoint['responses'] as $response) {
            if (isset($response['content']['application/json']['schema'])) {
                $this->inlineSchema($response['content']['application/json']['schema'], $schemas);
            }
        }
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> &$schemas
     */
    private function inlineSchema(array $schema, array &$schemas): void
    {
        if (isset($schema['\$ref'])) {
            return; // Already a reference
        }

        if (isset($schema['type']) && $schema['type'] === 'object' && isset($schema['properties'])) {
            // This could be extracted to a named schema, but for simplicity we inline
        }
    }

    /**
     * @param array<string, mixed> $responses
     * @return array<string, mixed>
     */
    private function formatResponses(array $responses): array
    {
        $formatted = [];

        foreach ($responses as $code => $response) {
            $formatted[$code] = [
                'description' => $response['description'] ?? 'Response',
            ];

            if (isset($response['content'])) {
                $formatted[$code]['content'] = $response['content'];
            }
        }

        return $formatted;
    }

    /**
     * @param array<string, mixed> $security
     * @return array<int, array<string, array<int, string>>>
     */
    private function buildSecurityRequirements(array $security): array
    {
        $requirements = [];

        foreach ($security as $name => $scheme) {
            $requirements[] = [$name => []];
        }

        return $requirements;
    }

    public function writeJson(array $spec, string $outputPath): void
    {
        $dir = dirname($outputPath);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($outputPath, json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function writeYaml(array $spec, string $outputPath): void
    {
        $dir = dirname($outputPath);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($outputPath, Yaml::dump($spec, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    }
}