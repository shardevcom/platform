<?php

declare(strict_types=1);

namespace Shardev\Documentation\Contracts;

use Shardev\Documentation\DTO\EndpointDocumentation;
use Shardev\Documentation\DTO\GraphQLDocumentation;

/**
 * Contract for generating API documentation from various sources.
 *
 * Boundary: abstraction over documentation generation strategies.
 * Implementations can parse routes, controllers, GraphQL schema, etc.
 */
interface DocumentationGenerator
{
    /**
     * Generate REST API documentation (OpenAPI 3.1).
     *
     * @return array<string, mixed> OpenAPI 3.1 specification
     */
    public function generateRest(): array;

    /**
     * Generate GraphQL API documentation (SDL + introspection).
     */
    public function generateGraphql(): GraphQLDocumentation;

    /**
     * Generate combined documentation for both REST and GraphQL.
     *
     * @return array<string, mixed> Combined specification
     */
    public function generateCombined(): array;

    /**
     * Get supported output formats.
     *
     * @return list<string>
     */
    public function supportedFormats(): array;

    /**
     * Export documentation to specific format.
     */
    public function export(string $format, string $outputPath): void;
}