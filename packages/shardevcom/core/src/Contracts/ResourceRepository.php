<?php

declare(strict_types=1);

namespace Shardevcom\Core\Contracts;

/**
 * Boundary: identity of an authorized resource access point.
 * A repository exists because a resource needs an authorization +
 * validation + lifecycle gate before persistence — never merely because
 * a model exists. Operation surface and lifecycle live in the abstract
 * ResourceRepository (T-051); this contract pins the stable identity that
 * the registry, authorization manager and adapters depend on.
 */
interface ResourceRepository
{
    public function resourceName(): string;

    /**
     * @return list<string> operation names this repository supports
     */
    public function supportedOperations(): array;
}
