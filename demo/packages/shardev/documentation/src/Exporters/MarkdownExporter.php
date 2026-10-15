<?php

declare(strict_types=1);

namespace Shardevcom\Documentation\Exporters;

use Illuminate\Support\Facades\File;
use Shardevcom\Documentation\DTO\GraphQLDocumentation;

/**
 * Exports documentation as Markdown files.
 *
 * Boundary: generates human-readable Markdown documentation from API specs.
 */
final class MarkdownExporter
{
    public function export(array $openapi, GraphQLDocumentation $graphql, string $outputPath): void
    {
        $dir = dirname($outputPath);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        // Generate main README.md
        $readme = $this->generateReadme($openapi, $graphql);
        File::put($outputPath, $readme);

        // Generate REST endpoints markdown
        $restDir = $dir.'/rest';
        if (! File::isDirectory($restDir)) {
            File::makeDirectory($restDir, 0755, true);
        }

        foreach ($openapi['paths'] ?? [] as $path => $methods) {
            $filename = Str::replace('/', '-', trim($path, '/')).'.md';
            $content = $this->generateEndpointMarkdown($path, $methods, $openapi);
            File::put($restDir.'/'.$filename, $content);
        }

        // Generate GraphQL markdown
        $graphqlDir = $dir.'/graphql';
        if (! File::isDirectory($graphqlDir)) {
            File::makeDirectory($graphqlDir, 0755, true);
        }

        $graphqlMd = $this->generateGraphQLMarkdown($graphql);
        File::put($graphqlDir.'/schema.md', $graphqlMd);
    }

    private function generateReadme(array $openapi, GraphQLDocumentation $graphql): string
    {
        $info = $openapi['info'] ?? [];
        $servers = $openapi['servers'] ?? [];
        $tags = $openapi['tags'] ?? [];
        $title = $info['title'] ?? 'API Documentation';
        $version = $info['version'] ?? '1.0.0';

        $md = "# {$title}\n\n";
        $md .= "**Version:** {$version}\n\n";

        if (! empty($info['description'])) {
            $md .= "{$info['description']}\n\n";
        }

        if (! empty($servers)) {
            $md .= "## Servers\n\n";
            foreach ($servers as $server) {
                $md .= "- **{$server['url']}** - {$server['description']}\n";
            }
            $md .= "\n";
        }

        if (! empty($tags)) {
            $md .= "## Tags\n\n";
            foreach ($tags as $tag) {
                $md .= "- **{$tag['name']}**: {$tag['description']}\n";
            }
            $md .= "\n";
        }

        $md .= "## REST Endpoints\n\n";
        $md .= "See [rest/ directory](./rest/) for detailed endpoint documentation.\n\n";

        $md .= "## GraphQL API\n\n";
        $md .= "See [graphql/schema.md](./graphql/schema.md) for GraphQL schema documentation.\n\n";

        $md .= "## Authentication\n\n";
        foreach (($openapi['components']['securitySchemes'] ?? []) as $name => $scheme) {
            $md .= "- **{$name}**: {$scheme['description']}\n";
        }

        return $md;
    }

    private function generateEndpointMarkdown(string $path, array $methods, array $openapi): string
    {
        $md = "# {$path}\n\n";

        foreach ($methods as $method => $operation) {
            $md .= "## ".strtoupper($method)."\n\n";

            if (! empty($operation['summary'])) {
                $md .= "**Summary:** {$operation['summary']}\n\n";
            }

            if (! empty($operation['description'])) {
                $md .= "{$operation['description']}\n\n";
            }

            if (! empty($operation['tags'])) {
                $md .= "**Tags:** ".implode(', ', $operation['tags'])."\n\n";
            }

            if (! empty($operation['parameters'])) {
                $md .= "### Parameters\n\n";
                $md .= "| Name | In | Required | Type | Description |\n";
                $md .= "|------|----|----------|------|-------------|\n";

                foreach ($operation['parameters'] as $param) {
                    $required = $param['required'] ? 'Yes' : 'No';
                    $type = $param['schema']['type'] ?? 'string';
                    $md .= "| {$param['name']} | {$param['in']} | {$required} | {$type} | {$param['description']} |\n";
                }
                $md .= "\n";
            }

            if (! empty($operation['requestBody'])) {
                $md .= "### Request Body\n\n";
                $content = $operation['requestBody']['content']['application/json'] ?? [];
                if (! empty($content['schema'])) {
                    $md .= $this->schemaToMarkdown($content['schema'], $openapi['components']['schemas'] ?? []);
                }
                if (! empty($content['example'])) {
                    $md .= "**Example:**\n```json\n".json_encode($content['example'], JSON_PRETTY_PRINT)."\n```\n\n";
                }
            }

            if (! empty($operation['responses'])) {
                $md .= "### Responses\n\n";
                foreach ($operation['responses'] as $code => $response) {
                    $md .= "#### {$code}\n\n";
                    $md .= "{$response['description']}\n\n";

                    if (! empty($response['content']['application/json']['schema'])) {
                        $md .= $this->schemaToMarkdown($response['content']['application/json']['schema'], $openapi['components']['schemas'] ?? []);
                    }
                }
            }

            if (! empty($operation['security'])) {
                $md .= "### Security\n\n";
                foreach ($operation['security'] as $scheme) {
                    foreach ($scheme as $name => $scopes) {
                        $md .= "- {$name}\n";
                    }
                }
                $md .= "\n";
            }
        }

        return $md;
    }

    private function schemaToMarkdown(array $schema, array $components): string
    {
        if (isset($schema['\$ref'])) {
            $ref = str_replace('#/components/schemas/', '', $schema['\$ref']);
            return "**Schema:** [`{$ref}`](#{$ref})\n\n";
        }

        $md = "**Schema:**\n```json\n".json_encode($schema, JSON_PRETTY_PRINT)."\n```\n\n";

        return $md;
    }

    private function generateGraphQLMarkdown(GraphQLDocumentation $graphql): string
    {
        $md = "# GraphQL Schema\n\n";

        if (! empty($graphql->scalars)) {
            $md .= "## Scalars\n\n";
            foreach ($graphql->scalars as $scalar) {
                $md .= "- **{$scalar['name']}**: {$scalar['description']}\n";
            }
            $md .= "\n";
        }

        if (! empty($graphql->types)) {
            $md .= "## Types\n\n";
            foreach ($graphql->types as $type) {
                $md .= "### {$type['name']}\n\n";
                $md .= $type['description']."\n\n";

                if (! empty($type['fields'])) {
                    $md .= "| Field | Type | Required | Description |\n";
                    $md .= "|-------|------|----------|-------------|\n";

                    foreach ($type['fields'] as $name => $field) {
                        $req = $field['required'] ? 'Yes' : 'No';
                        $md .= "| {$name} | {$field['type']} | {$req} | {$field['description']} |\n";
                    }
                    $md .= "\n";
                }
            }
        }

        if (! empty($graphql->queries)) {
            $md .= "## Queries\n\n";
            foreach ($graphql->queries as $name => $field) {
                $md .= "### {$name}\n\n";
                $md .= "**Returns:** {$field['type']}\n\n";
                $md .= $field['description']."\n\n";
            }
        }

        if (! empty($graphql->mutations)) {
            $md .= "## Mutations\n\n";
            foreach ($graphql->mutations as $name => $field) {
                $md .= "### {$name}\n\n";
                $md .= "**Returns:** {$field['type']}\n\n";
                $md .= $field['description']."\n\n";
            }
        }

        $md .= "---\n\n*Generated by shardev/documentation*\n";

        return $md;
    }
}