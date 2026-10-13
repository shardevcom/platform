<?php

declare(strict_types=1);

namespace Shardevcom\Core\Lifecycle;

use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\ResourceAction;

/**
 * Immutable description of one authorized operation crossing the lifecycle
 * (T-050). Pure data handed to hooks: it lets audit, events and metrics
 * implementations stay generic — they never need the operation callable,
 * the repository or the persistence layer.
 *
 * Attributes are an OPTIONAL, SAFE echo of what the caller asked for (the
 * changed field names, the requested page, ...). They must never carry
 * secrets, credentials or raw payloads: hooks log them.
 */
final class ActionInvocation
{
    /**
     * @param array<string, mixed> $attributes safe, loggable echoes only
     */
    public function __construct(
        private string $resource,
        private string $action,
        private ApplicationContext $context,
        private array $attributes = [],
    ) {
    }

    public function resource(): string
    {
        return $this->resource;
    }

    /**
     * Canonical or custom action segment (see ResourceAction::segment).
     */
    public function action(): string
    {
        return $this->action;
    }

    public function permission(): string
    {
        return $this->resource.'.'.$this->action;
    }

    public function context(): ApplicationContext
    {
        return $this->context;
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public static function forAction(string $resource, ResourceAction|string $action, ApplicationContext $context, array $attributes = []): self
    {
        return new self($resource, ResourceAction::segment($action), $context, $attributes);
    }
}