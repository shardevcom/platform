<?php

declare(strict_types=1);

namespace Shardevcom\Documentation\Exporters;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Shardevcom\Documentation\DTO\GraphQLDocumentation;

/**
 * Exports GraphQL documentation (SDL and introspection).
 *
 * Boundary: formats GraphQL schema for documentation.
 */
final class GraphQLExporter
{
    public function export(array $schema): GraphQLDocumentation
    {
        return new GraphQLDocumentation(
            schemaSdl: $schema['schema_sdl'] ?? '',
            types: $schema['types'] ?? [],
            queries: $schema['queries'] ?? [],
            mutations: $schema['mutations'] ?? [],
            subscriptions: $schema['subscriptions'] ?? [],
            directives: $schema['directives'] ?? [],
            scalars: $schema['scalars'] ?? [],
            introspection: $this->generateIntrospection($schema),
        );
    }

    private function generateIntrospection(array $schema): array
    {
        $types = [];

        // Add built-in scalars
        foreach (['String', 'Int', 'Float', 'Boolean', 'ID'] as $scalar) {
            $types[] = [
                'kind' => 'SCALAR',
                'name' => $scalar,
                'description' => 'Built-in scalar type',
            ];
        }

        // Add custom scalars
        foreach ($schema['scalars'] as $scalar) {
            $types[] = [
                'kind' => 'SCALAR',
                'name' => $scalar['name'],
                'description' => $scalar['description'] ?? 'Custom scalar',
            ];
        }

        // Add object types
        foreach ($schema['types'] as $type) {
            $fields = [];
            foreach ($type['fields'] as $name => $field) {
                $fields[] = [
                    'name' => $name,
                    'description' => $field['description'] ?? '',
                    'type' => $this->typeToIntrospection($field['type']),
                ];
            }

            $types[] = [
                'kind' => 'OBJECT',
                'name' => $type['name'],
                'description' => $type['description'] ?? '',
                'fields' => $fields,
            ];
        }

        // Query type
        if (! empty($schema['queries'])) {
            $fields = [];
            foreach ($schema['queries'] as $name => $field) {
                $fields[] = [
                    'name' => $name,
                    'description' => $field['description'] ?? '',
                    'type' => $this->typeToIntrospection($field['type']),
                ];
            }

            $types[] = [
                'kind' => 'OBJECT',
                'name' => 'Query',
                'description' => 'Root query type',
                'fields' => $fields,
            ];
        }

        // Mutation type
        if (! empty($schema['mutations'])) {
            $fields = [];
            foreach ($schema['mutations'] as $name => $field) {
                $fields[] = [
                    'name' => $name,
                    'description' => $field['description'] ?? '',
                    'type' => $this->typeToIntrospection($field['type']),
                ];
            }

            $types[] = [
                'kind' => 'OBJECT',
                'name' => 'Mutation',
                'description' => 'Root mutation type',
                'fields' => $fields,
            ];
        }

        return [
            '__schema' => [
                'queryType' => ['name' => 'Query'],
                'mutationType' => ! empty($schema['mutations']) ? ['name' => 'Mutation'] : null,
                'subscriptionType' => ! empty($schema['subscriptions']) ? ['name' => 'Subscription'] : null,
                'types' => $types,
                'directives' => array_map(function ($d) {
                    return [
                        'name' => $d['name'],
                        'description' => $d['description'] ?? '',
                        'locations' => ['FIELD_DEFINITION'],
                    ];
                }, $schema['directives'] ?? []),
            ],
        ];
    }

    private function typeToIntrospection(string $type): array
    {
        // Remove ! for non-null
        $nonNull = Str::endsWith($type, '!');
        if ($nonNull) {
            $type = substr($type, 0, -1);
        }

        // Remove [] for list
        $isList = false;
        if (Str::startsWith($type, '[') && Str::endsWith($type, ']')) {
            $isList = true;
            $type = substr($type, 1, -1);
        }

        $kind = match ($type) {
            'String', 'Int', 'Float', 'Boolean', 'ID' => 'SCALAR',
            default => 'OBJECT',
        };

        $result = [
            'kind' => $kind,
            'name' => $kind === 'SCALAR' ? $type : null,
            'ofType' => null,
        ];

        if ($isList) {
            $result = [
                'kind' => 'LIST',
                'name' => null,
                'ofType' => $result,
            ];
        }

        if ($nonNull) {
            $result = [
                'kind' => 'NON_NULL',
                'name' => null,
                'ofType' => $result,
            ];
        }

        return $result;
    }

    public function writeSdl(GraphQLDocumentation $doc, string $outputPath): void
    {
        $dir = dirname($outputPath);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($outputPath, $doc->schemaSdl);
    }

    public function writeIntrospection(GraphQLDocumentation $doc, string $outputPath): void
    {
        $dir = dirname($outputPath);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($outputPath, json_encode($doc->introspection, JSON_PRETTY_PRINT));
    }
}