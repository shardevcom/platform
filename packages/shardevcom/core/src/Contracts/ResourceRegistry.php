<?php

declare(strict_types=1);

namespace Shardevcom\Core\Contracts;

use Shardevcom\Resources\ResourceDefinition;

/**
 * Boundary: catalogue of addressable resources. Adapters (REST routes,
 * GraphQL schema) and repositories resolve definitions here instead of
 * hard-coding resource metadata, so allow-lists stay in one place.
 */
interface ResourceRegistry
{
    public function register(ResourceDefinition $definition): void;

    public function get(string $name): ResourceDefinition;

    public function has(string $name): bool;

    /**
     * @return array<string, ResourceDefinition> keyed by resource name
     */
    public function all(): array;
}
