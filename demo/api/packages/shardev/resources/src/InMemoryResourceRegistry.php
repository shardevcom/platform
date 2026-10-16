<?php

declare(strict_types=1);

namespace Shardevcom\Resources;

use Shardevcom\Core\Contracts\ResourceRegistry as RegistryContract;
use Shardevcom\Core\Exceptions\Conflict;
use Shardevcom\Core\Exceptions\ResourceNotFound;

/**
 * In-process catalogue of resource definitions (T-021).
 *
 * Boundary implementation of the Core ResourceRegistry contract: duplicate
 * registration is a Conflict, unknown lookup is a domain ResourceNotFound
 * (never a raw null the caller could dereference, never an HTTP 404).
 */
final class InMemoryResourceRegistry implements RegistryContract
{
    /** @var array<string, ResourceDefinition> */
    private array $definitions = [];

    public function register(ResourceDefinition $definition): void
    {
        $name = $definition->name();

        if (isset($this->definitions[$name])) {
            throw new Conflict("Resource [{$name}] is already registered.", ['resource' => $name]);
        }

        $this->definitions[$name] = $definition;
    }

    public function get(string $name): ResourceDefinition
    {
        if (! isset($this->definitions[$name])) {
            throw ResourceNotFound::for('resources', $name);
        }

        return $this->definitions[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->definitions[$name]);
    }

    /**
     * @return array<string, ResourceDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }
}
