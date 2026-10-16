<?php

declare(strict_types=1);

namespace Shardevcom\Core\Contracts;

/**
 * Metrics contract (T-121).
 *
 * Transport-agnostic, vendor-free. Implementations may wrap Prometheus client,
 * StatsD, Datadog, custom collectors, etc.
 */
interface Meter
{
    /**
     * Increment a counter.
     *
     * @param array<string, mixed> $tags dimensions (e.g., resource, action, status, tenant)
     */
    public function counter(string $name, array $tags = []): void;

    /**
     * Record a gauge value (current value of a metric).
     *
     * @param array<string, mixed> $tags
     */
    public function gauge(string $name, float $value, array $tags = []): void;

    /**
     * Record a histogram/timing value (latency, size, etc.).
     *
     * @param array<string, mixed> $tags
     */
    public function histogram(string $name, float $value, array $tags = []): void;

    /**
     * Time a callable and record its duration as a histogram.
     *
     * @param array<string, mixed> $tags
     * @return mixed the return value of the callable
     */
    public function time(string $name, callable $callable, array $tags = []): mixed;

    /**
     * Create a child meter with default tags (for scoped instrumentation).
     *
     * @param array<string, mixed> $defaultTags
     */
    public function withTags(array $defaultTags): self;
}