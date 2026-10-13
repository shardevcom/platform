<?php

declare(strict_types=1);

namespace Shardev\Documentation\Parsers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use Shardev\Documentation\DTO\EndpointDocumentation;

/**
 * Parses controller classes and FormRequests to enrich endpoint documentation.
 *
 * Boundary: extracts documentation from PHPDoc, attributes, and validation rules.
 */
final class ControllerParser
{
    public function enrich(array $endpoints, array $config): array
    {
        if (! ($config['enabled'] ?? true)) {
            return $endpoints;
        }

        $controllerPaths = $config['paths'] ?? ['app/Http/Controllers'];
        $controllers = $this->discoverControllers($controllerPaths);

        // Index endpoints by operationId for quick lookup
        $endpointIndex = [];
        foreach ($endpoints as $endpoint) {
            $endpointIndex[$endpoint->operationId] = $endpoint;
        }

        foreach ($controllers as $controller) {
            $this->parseController($controller, $endpointIndex, $config);
        }

        // Also parse FormRequests
        if (($config['form_requests']['enabled'] ?? true)) {
            $this->parseFormRequests($config['form_requests']['paths'] ?? [], $endpointIndex);
        }

        return array_values($endpointIndex);
    }

    /**
     * @return array<int, ReflectionClass>
     */
    private function discoverControllers(array $paths): array
    {
        $controllers = [];

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

                $className = $this->fileToClassName($file, $basePath);
                if (! class_exists($className)) {
                    continue;
                }

                $reflection = new ReflectionClass($className);
                if ($reflection->isAbstract() || $reflection->isInterface()) {
                    continue;
                }

                $controllers[] = $reflection;
            }
        }

        return $controllers;
    }

    private function fileToClassName(\SplFileInfo $file, string $basePath): string
    {
        $relative = $file->getRelativePathname();
        $relative = Str::replaceLast('.php', '', $relative);
        $namespace = Str::replace('/', '\\', Str::replaceFirst('app/', 'App\\', $basePath));

        return $namespace.'\\'.$relative;
    }

    private function parseController(ReflectionClass $controller, array &$endpointIndex, array $config): void
    {
        $classDocBlock = $this->parseDocBlock($controller->getDocComment() ?? '');

        foreach ($controller->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->name !== $controller->name) {
                continue; // Skip inherited methods
            }

            $methodDocBlock = $this->parseDocBlock($method->getDocComment() ?? '');
            $operationId = $this->guessOperationId($controller, $method);

            if (! isset($endpointIndex[$operationId])) {
                // Try alternative operationId patterns
                $altIds = $this->generateAlternativeIds($controller, $method);
                foreach ($altIds as $altId) {
                    if (isset($endpointIndex[$altId])) {
                        $operationId = $altId;
                        break;
                    }
                }
            }

            if (! isset($endpointIndex[$operationId])) {
                continue;
            }

            $endpoint = $endpointIndex[$operationId];

            // Enrich with controller documentation
            $endpointIndex[$operationId] = new EndpointDocumentation(
                method: $endpoint->method,
                path: $endpoint->path,
                summary: $methodDocBlock['summary'] ?? $endpoint->summary,
                description: $methodDocBlock['description'] ?? $classDocBlock['description'] ?? $endpoint->description,
                operationId: $endpoint->operationId,
                tags: array_merge($endpoint->tags, $methodDocBlock['tags'] ?? []),
                parameters: array_merge($endpoint->parameters, $this->extractMethodParameters($method)),
                requestBody: $this->extractRequestBody($method, $methodDocBlock) ?? $endpoint->requestBody,
                responses: array_merge($endpoint->responses, $this->extractResponses($methodDocBlock)),
                security: $endpoint->security,
                deprecated: $methodDocBlock['deprecated'] ?? $endpoint->deprecated,
                extensions: array_merge($endpoint->extensions, $methodDocBlock['extensions'] ?? []),
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseDocBlock(?string $docComment): array
    {
        if (! $docComment) {
            return [];
        }

        $lines = explode("\n", $docComment);
        $result = [
            'summary' => '',
            'description' => '',
            'tags' => [],
            'parameters' => [],
            'responses' => [],
            'deprecated' => false,
            'extensions' => [],
        ];

        $inDescription = false;
        $descriptionLines = [];

        foreach ($lines as $line) {
            $line = trim($line, " *\t");

            if (Str::startsWith($line, '@')) {
                $inDescription = true;
                $parts = explode(' ', $line, 2);
                $tag = $parts[0];
                $value = $parts[1] ?? '';

                match ($tag) {
                    '@summary', '@description' => $result['summary'] = $value,
                    '@description' => $result['description'] = $value,
                    '@tag' => $result['tags'][] = $value,
                    '@param' => $result['parameters'][] = $this->parseParamTag($value),
                    '@response', '@return' => $result['responses'][] = $this->parseResponseTag($value),
                    '@deprecated' => $result['deprecated'] = true,
                    '@extends' => $result['extensions']['x-extends'] = $value,
                    default => null,
                };
            } elseif (! $inDescription && $line !== '') {
                $result['summary'] = $line;
            } elseif ($inDescription && $line !== '') {
                $descriptionLines[] = $line;
            }
        }

        if (! empty($descriptionLines)) {
            $result['description'] = implode("\n", $descriptionLines);
        }

        return $result;
    }

    private function parseParamTag(string $value): array
    {
        // @param type $name description
        if (preg_match('/^(\S+)\s+\$(\S+)\s*(.*)$/', $value, $matches)) {
            return [
                'type' => $matches[1],
                'name' => $matches[2],
                'description' => $matches[3] ?? '',
            ];
        }

        return ['raw' => $value];
    }

    private function parseResponseTag(string $value): array
    {
        // @response code description
        if (preg_match('/^(\d{3})\s*(.*)$/', $value, $matches)) {
            return [
                'code' => (int) $matches[1],
                'description' => $matches[2] ?? '',
            ];
        }

        return ['raw' => $value];
    }

    private function guessOperationId(ReflectionClass $controller, ReflectionMethod $method): string
    {
        $controllerName = Str::replace('Controller', '', $controller->getShortName());
        $methodName = $method->getName();

        // Map common method names to actions
        $actionMap = [
            'index' => 'list',
            'store' => 'create',
            'show' => 'get',
            'update' => 'update',
            'destroy' => 'delete',
            'export' => 'export',
            'import' => 'import',
        ];

        $action = $actionMap[$methodName] ?? Str::camel($methodName);
        $resource = Str::singular(Str::studly($controllerName));

        return Str::camel($action.'_'.$resource);
    }

    /**
     * @return array<string>
     */
    private function generateAlternativeIds(ReflectionClass $controller, ReflectionMethod $method): array
    {
        $controllerName = $controller->getShortName();
        $methodName = $method->getName();

        return [
            Str::camel($methodName.'_'.$controllerName),
            Str::camel($controllerName.'_'.$methodName),
            $methodName,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractMethodParameters(ReflectionMethod $method): array
    {
        $parameters = [];

        foreach ($method->getParameters() as $param) {
            if ($param->getName() === 'request' || $param->getName() === '$request') {
                continue; // Skip Request injection
            }

            $type = $param->getType();
            $typeName = $type ? (string) $type : 'mixed';

            $parameters[] = [
                'name' => $param->getName(),
                'in' => 'path', // Default, could be improved
                'required' => ! $param->isOptional(),
                'schema' => $this->typeToSchema($typeName),
                'description' => 'Parameter: '.$param->getName(),
            ];
        }

        return $parameters;
    }

    private function typeToSchema(string $type): array
    {
        return match ($type) {
            'int', 'integer' => ['type' => 'integer'],
            'float', 'double' => ['type' => 'number', 'format' => 'float'],
            'bool', 'boolean' => ['type' => 'boolean'],
            'string' => ['type' => 'string'],
            'array' => ['type' => 'array'],
            default => ['type' => 'string'],
        };
    }

    private function extractRequestBody(ReflectionMethod $method, array $docBlock): ?array
    {
        // Check for @body or @requestBody tag in docblock
        // This would need custom tags like @body {"field": "type"}

        foreach ($method->getParameters() as $param) {
            $type = $param->getType();
            if (! $type) {
                continue;
            }

            $typeName = (string) $type;
            // Check if it's a FormRequest or DTO
            if (class_exists($typeName) && (is_subclass_of($typeName, \Illuminate\Foundation\Http\FormRequest::class) || Str::endsWith($typeName, 'Data'))) {
                return $this->parseFormRequestClass($typeName);
            }
        }

        return null;
    }

    private function parseFormRequestClass(string $className): ?array
    {
        if (! class_exists($className)) {
            return null;
        }

        $reflection = new ReflectionClass($className);
        $rulesMethod = $reflection->getMethod('rules') ?? null;

        if (! $rulesMethod) {
            return null;
        }

        $instance = $reflection->newInstanceWithoutConstructor();
        $rules = $instance->rules();

        $properties = [];
        $required = [];

        foreach ($rules as $field => $rule) {
            $properties[$field] = $this->ruleToSchema($rule);
            if (Str::contains($rule, 'required')) {
                $required[] = $field;
            }
        }

        return [
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => $properties,
                        'required' => $required,
                    ],
                    'example' => $this->generateExample($properties),
                ],
            ],
        ];
    }

    private function ruleToSchema(string $rule): array
    {
        $rules = explode('|', $rule);
        $schema = ['type' => 'string'];

        foreach ($rules as $r) {
            if (Str::startsWith($r, 'integer') || Str::startsWith($r, 'numeric')) {
                $schema['type'] = 'integer';
            } elseif (Str::startsWith($r, 'boolean')) {
                $schema['type'] = 'boolean';
            } elseif (Str::startsWith($r, 'array')) {
                $schema['type'] = 'array';
            } elseif (Str::startsWith($r, 'json')) {
                $schema['type'] = 'object';
            } elseif (Str::startsWith($r, 'date')) {
                $schema['type'] = 'string';
                $schema['format'] = 'date';
            } elseif (Str::startsWith($r, 'email')) {
                $schema['type'] = 'string';
                $schema['format'] = 'email';
            } elseif (Str::startsWith($r, 'uuid')) {
                $schema['type'] = 'string';
                $schema['format'] = 'uuid';
            }
        }

        return $schema;
    }

    private function generateExample(array $properties): array
    {
        $example = [];

        foreach ($properties as $field => $schema) {
            $type = $schema['type'] ?? 'string';

            $example[$field] = match ($type) {
                'integer' => 1,
                'number' => 1.5,
                'boolean' => true,
                'array' => [],
                'object' => new \stdClass(),
                default => 'example_'.$field,
            };
        }

        return $example;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractResponses(array $docBlock): array
    {
        $responses = [];

        foreach ($docBlock['responses'] ?? [] as $response) {
            if (! isset($response['code'])) {
                continue;
            }

            $responses[(string) $response['code']] = [
                'description' => $response['description'] ?? 'Response',
            ];
        }

        return $responses;
    }

    private function parseFormRequests(array $paths, array &$endpointIndex): void
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

                $className = $this->fileToClassName($file, $basePath);
                if (! class_exists($className)) {
                    continue;
                }

                // FormRequest classes are matched by convention to endpoints
                // This is a simplified approach - a real implementation would
                // match by controller method parameter type hints
            }
        }
    }
}