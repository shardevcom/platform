<?php

declare(strict_types=1);

namespace Shardevcom\Core\Contracts;

/**
 * Distributed tracing contract (T-121).
 *
 * Transport-agnostic, vendor-free. Implementations may wrap OpenTelemetry,
 * Zipkin, Jaeger, custom tracers, etc.
 */
interface Tracer
{
    /**
     * Start a new span. Returns a Span that must be ended.
     *
     * @param array<string, mixed> $attributes initial attributes
     */
    public function startSpan(string $name, array $attributes = []): Span;

    /**
     * Get the current active span, if any.
     */
    public function currentSpan(): ?Span;

    /**
     * Execute a callable within a new span.
     *
     * @param array<string, mixed> $attributes
     * @return mixed the return value of the callable
     */
    public function inSpan(string $name, callable $callable, array $attributes = []): mixed;
}