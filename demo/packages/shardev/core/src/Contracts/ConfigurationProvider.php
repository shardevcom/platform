<?php

declare(strict_types=1);

namespace Shardevcom\Core\Contracts;

use Shardevcom\Core\ApplicationContext;

/**
 * Boundary: one addressable configuration domain per provider.
 * Any package registers a provider under its own key; the registry
 * aggregates them (with per-provider partial results), so clients fetch
 * core + feature config through a single endpoint.
 */
interface ConfigurationProvider
{
    public function key(): string;

    public function visibility(): ConfigurationVisibility;

    public function resolve(ApplicationContext $context): mixed;
}
