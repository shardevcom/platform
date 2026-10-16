<?php

declare(strict_types=1);

namespace Shardevcom\Core\Contracts;

/**
 * Vendor-agnostic read-through cache port (T-121).
 *
 * Core never binds a concrete cache library: this is the minimal surface a
 * decorator such as CachedProductRepository needs. An infrastructure adapter
 * wraps PSR-16, Laravel Cache, Redis, ... behind it, so the pattern documented
 * in the README stays free of vendor lock.
 *
 * The contract is intentionally tiny: a decorator only needs to read a value,
 * store it with a TTL, and drop it on write. Anything richer belongs to the
 * concrete adapter, not to the boundary.
 */
interface CacheStore
{
    /**
     * @return mixed null when the key is absent or already expired
     */
    public function get(string $key): mixed;

    public function put(string $key, mixed $value, int $ttlSeconds): void;

    public function forget(string $key): void;
}
