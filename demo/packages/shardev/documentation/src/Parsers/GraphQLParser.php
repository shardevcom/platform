<?php

declare(strict_types=1);

namespace Shardev\Documentation\Parsers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Parses GraphQL schema and resolvers to extract documentation.
 *
 * Boundary: extracts GraphQL schema information for documentation generation.
 */
final class GraphQLParser
{
    public function parse(array $config): array
    {
        $schema = [
            'types' => [],
            'queries' => [],
            'mutations' => [],
            'subscriptions' => [],
            'directives' => [],
            'scalars' => [],
        ];

        // Parse schema.graphql file
        $schemaPath = $config['schema_path'] ?? 'graphql/schema.graphql';
        $fullSchemaPath = base_path($schemaPath);

        if (File::exists($fullSchemaPath)) {
            $schemaContent = File::get($fullSchemaPath);
            $schema = $this->parseSchema($schemaContent, $schema);
        }

        // Parse resolvers for additional documentation
        $resolverPaths = $config['resolvers_paths'] ?? ['app/GraphQL', 'packages/*/src/Graphql/Resolvers'];
        if (($config['extract_docblocks'] ?? true)) {
            $schema = $this->parseResolvers($resolverPaths, $schema);
        }

        // Generate SDL
        $schemaSdl = $this->generateSdl($schema);

        return [
            'schema_sdl' => $schemaSdl,
            'types' => $schema['types'],
            'queries' => $schema['queries'],
            'mutations' => $schema['mutations'],
            'subscriptions' => $schema['subscriptions'],
            'directives' => $schema['directives'],
            'scalars' => $schema['scalars'],
        ];
    }

    private function parseSchema(string $content, array $schema): array
    {
        // Simple SDL parser - in production, use a proper GraphQL parser like
        // webonyx/graphql-php's SchemaParser
        $lines = explode("\n", $content);
        $currentType = null;
        $inType = false;
        $typeBuffer = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (Str::startsWith($trimmed, '#') || $trimmed === '') {
                continue;
            }

            // Type definitions: type Query {, type Mutation {, type Product {, etc.
            if (preg_match('/^(type|input|interface|union|enum)\s+(\w+)/', $trimmed, $matches)) {
                if ($inType && $currentType) {
                    $schema = $this->finalizeType($schema, $currentType, $typeBuffer);
                }

                $currentType = [
                    'kind' => $matches[1],
                    'name' => $matches[2],
                    'fields' => [],
                    'description' => '',
                ];
                $typeBuffer = [];
                $inType = true;
                continue;
            }

            // Closing brace
            if ($trimmed === '}' && $inType) {
                if ($currentType) {
                    $schema = $this->finalizeType($schema, $currentType, $typeBuffer);
                }
                $currentType = null;
                $inType = false;
                continue;
            }

            if ($inType && $currentType) {
                $typeBuffer[] = $line;
            }
        }

        return $schema;
    }

    private function finalizeType(array $schema, array $currentType, array $buffer): array
    {
        $fields = [];

        foreach ($buffer as $line) {
            $trimmed = trim($line);

            if (Str::startsWith($trimmed, '#') || $trimmed === '') {
                continue;
            }

            // Field: fieldName: Type!
            if (preg_match('/^(\w+)\s*:\s*([^!{}]+!?)/', $trimmed, $matches)) {
                $fields[$matches[1]] = [
                    'type' => trim($matches[2]),
                    'required' => Str::endsWith($matches[2], '!'),
                    'description' => '',
                ];
            }
        }

        $typeInfo = [
            'name' => $currentType['name'],
            'kind' => $currentType['kind'],
            'fields' => $fields,
            'description' => $currentType['description'],
        ];

        switch ($currentType['kind']) {
            case 'type':
                if ($currentType['name'] === 'Query') {
                    $schema['queries'] = $fields;
                } elseif ($currentType['name'] === 'Mutation') {
                    $schema['mutations'] = $fields;
                } elseif ($currentType['name'] === 'Subscription') {
                    $schema['subscriptions'] = $fields;
                } else {
                    $schema['types'][$currentType['name']] = $typeInfo;
                }
                break;
            case 'input':
                $schema['types'][$currentType['name']] = $typeInfo;
                break;
            case 'scalar':
                $schema['scalars'][$currentType['name']] = $typeInfo;
                break;
            case 'directive':
                $schema['directives'][$currentType['name']] = $typeInfo;
                break;
        }

        return $schema;
    }

    private function parseResolvers(array $paths, array $schema): array
    {
        foreach ($paths as $basePath) {
            $fullPath = base_path($basePath);
            if (! File::isDirectory($fullPath)) {
                continue;
            }

            $files = File::allFiles($fullPath);
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                // Parse resolver classes for @doc directives or docblocks
                // This would extract field descriptions, deprecation, etc.
            }
        }

        return $schema;
    }

    private function generateSdl(array $schema): string
    {
        $lines = [];

        // Scalars
        foreach ($schema['scalars'] as $scalar) {
            $lines[] = 'scalar '.$scalar['name'];
        }

        // Directives
        foreach ($schema['directives'] as $directive) {
            $lines[] = 'directive @'.$directive['name'].' on FIELD_DEFINITION';
        }

        // Types
        foreach ($schema['types'] as $type) {
            if ($type['kind'] === 'input') {
                $lines[] = 'input '.$type['name'].' {';
            } else {
                $lines[] = 'type '.$type['name'].' {';
            }

            foreach ($type['fields'] as $name => $field) {
                $req = $field['required'] ? '!' : '';
                $lines[] = '  '.$name.': '.$field['type'].$req;
            }

            $lines[] = '}';
            $lines[] = '';
        }

        // Query
        if (! empty($schema['queries'])) {
            $lines[] = 'type Query {';
            foreach ($schema['queries'] as $name => $field) {
                $req = $field['required'] ? '!' : '';
                $lines[] = '  '.$name.': '.$field['type'].$req;
            }
            $lines[] = '}';
            $lines[] = '';
        }

        // Mutation
        if (! empty($schema['mutations'])) {
            $lines[] = 'type Mutation {';
            foreach ($schema['mutations'] as $name => $field) {
                $req = $field['required'] ? '!' : '';
                $lines[] = '  '.$name.': '.$field['type'].$req;
            }
            $lines[] = '}';
            $lines[] = '';
        }

        // Subscription
        if (! empty($schema['subscriptions'])) {
            $lines[] = 'type Subscription {';
            foreach ($schema['subscriptions'] as $name => $field) {
                $req = $field['required'] ? '!' : '';
                $lines[] = '  '.$name.': '.$field['type'].$req;
            }
            $lines[] = '}';
        }

        return implode("\n", $lines);
    }
}