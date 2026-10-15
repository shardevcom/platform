<?php

declare(strict_types=1);

namespace Shardevcom\Documentation\Parsers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Shardevcom\Documentation\DTO\EndpointDocumentation;

/**
 * Parses Laravel routes to extract endpoint information.
 *
 * Boundary: extracts raw route data without business logic.
 */
final class RouteParser
{
    public function parse(array $config): array
    {
        $includePatterns = $config['include'] ?? ['api/*', 'graphql'];
        $excludePatterns = $config['exclude'] ?? [];

        $routes = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            // Skip excluded patterns
            if ($this->matchesAny($uri, $excludePatterns)) {
                continue;
            }

            // Only include matching patterns
            if (! $this->matchesAny($uri, $includePatterns)) {
                continue;
            }

            $methods = $route->methods();
            if (in_array('HEAD', $methods)) {
                continue; // Skip HEAD, it's automatic
            }

            foreach ($methods as $method) {
                $routes[] = $this->parseRoute($route, $method, $uri);
            }
        }

        return $routes;
    }

    private function parseRoute(\Illuminate\Routing\Route $route, string $method, string $uri): EndpointDocumentation
    {
        $action = $route->getAction();
        $controller = $action['controller'] ?? null;

        return new EndpointDocumentation(
            method: strtoupper($method),
            path: '/'.$uri,
            summary: $this->generateSummary($method, $uri, $controller),
            description: null,
            operationId: $this->generateOperationId($method, $uri, $controller),
            tags: $this->extractTags($uri, $controller),
            parameters: $this->extractParameters($route),
            requestBody: null, // Will be enriched by ControllerParser
            responses: $this->generateDefaultResponses($method),
            security: $this->extractSecurity($route),
            deprecated: false,
            extensions: [],
        );
    }

    private function generateSummary(string $method, string $uri, ?string $controller): string
    {
        $resource = $this->extractResource($uri);
        $action = $this->methodToAction($method);

        if ($controller) {
            $className = class_basename($controller);
            return $action.' '.$resource.' ('.$className.')';
        }

        return $action.' '.$resource;
    }

    private function generateOperationId(string $method, string $uri, ?string $controller): string
    {
        $resource = $this->extractResource($uri);
        $action = Str::camel($this->methodToAction($method));

        return Str::camel($action.'_'.$resource);
    }

    private function extractResource(string $uri): string
    {
        // Remove platform prefix (admin/, employee/, etc.)
        $uri = preg_replace('#^(admin|employee|web)/#', '', $uri);
        // Remove api/v1 prefix
        $uri = preg_replace('#^api/v\d+/#', '', $uri);
        // Get the main resource
        $parts = explode('/', $uri);
        $resource = $parts[0] ?? 'unknown';

        return Str::singular(Str::studly($resource));
    }

    private function methodToAction(string $method): string
    {
        return match (strtoupper($method)) {
            'GET' => 'List',
            'POST' => 'Create',
            'PUT' => 'Update',
            'PATCH' => 'Patch',
            'DELETE' => 'Delete',
            default => ucfirst(strtolower($method)),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractTags(string $uri, ?string $controller): array
    {
        $tags = [];

        // Tag from platform prefix
        if (Str::startsWith($uri, 'admin/')) {
            $tags[] = 'Admin';
        } elseif (Str::startsWith($uri, 'employee/')) {
            $tags[] = 'Employee';
        } else {
            $tags[] = 'Web';
        }

        // Tag from resource
        $resource = $this->extractResource($uri);
        $tags[] = $resource;

        // Tag from controller namespace
        if ($controller) {
            if (Str::contains($controller, 'Product')) {
                $tags[] = 'Products';
            } elseif (Str::contains($controller, 'Configuration')) {
                $tags[] = 'Configuration';
            } elseif (Str::contains($controller, 'Widget')) {
                $tags[] = 'Widgets';
            } elseif (Str::contains($controller, 'Auth')) {
                $tags[] = 'Authentication';
            }
        }

        return array_unique($tags);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractParameters(\Illuminate\Routing\Route $route): array
    {
        $parameters = [];

        // Path parameters
        $pathParams = $route->parameterNames();
        foreach ($pathParams as $param) {
            $parameters[] = [
                'name' => $param,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
                'description' => 'Path parameter: '.$param,
            ];
        }

        // Query parameters (would need more sophisticated parsing)
        // For now, we'll rely on ControllerParser to enrich this

        return $parameters;
    }

    /**
     * @return array<string, mixed>
     */
    private function generateDefaultResponses(string $method): array
    {
        $responses = [
            'default' => [
                'description' => 'Unexpected error',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/Error'],
                    ],
                ],
            ],
        ];

        match (strtoupper($method)) {
            'GET' => $responses['200'] = [
                'description' => 'Successful response',
                'content' => [
                    'application/json' => [
                        'schema' => ['type' => 'object'],
                    ],
                ],
            ],
            'POST' => $responses['201'] = [
                'description' => 'Resource created',
                'content' => [
                    'application/json' => [
                        'schema' => ['type' => 'object'],
                    ],
                ],
            ],
            'PUT', 'PATCH' => $responses['200'] = [
                'description' => 'Resource updated',
                'content' => [
                    'application/json' => [
                        'schema' => ['type' => 'object'],
                    ],
                ],
            ],
            'DELETE' => $responses['204'] = [
                'description' => 'Resource deleted',
            ],
        };

        // Add common error responses
        $responses['401'] = ['description' => 'Unauthorized'];
        $responses['403'] = ['description' => 'Forbidden'];
        $responses['404'] = ['description' => 'Not found'];
        $responses['422'] = ['description' => 'Validation error'];

        return $responses;
    }

    private function extractSecurity(\Illuminate\Routing\Route $route): array
    {
        $middleware = $route->gatherMiddleware();

        $security = [];
        foreach ($middleware as $mw) {
            if (Str::startsWith($mw, 'auth:')) {
                $security[] = ['bearerAuth' => []];
                break;
            }
        }

        return $security;
    }

    private function matchesAny(string $uri, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $regex = Str::replace('*', '.*', preg_quote($pattern, '#'));
            if (preg_match('#^'.$regex.'$#', $uri)) {
                return true;
            }
        }

        return false;
    }
}