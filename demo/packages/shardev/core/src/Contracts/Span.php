<?php

declare(strict_types=1);

namespace Shardevcom\Core\Contracts;

/**
 * A single unit of traced work (T-121).
 *
 * Returned by Tracer::startSpan() and Tracer::inSpan(). The implementing
 * tracer decides what happens with the events/attributes; this contract only
 * fixes the surface the lifecycle and use cases rely on.
 */
interface Span
{
    /**
     * Set an attribute on the span.
     */
    public function setAttribute(string $key, mixed $value): self;

    /**
     * Add an event to the span.
     *
     * @param array<string, mixed> $attributes
     */
    public function addEvent(string $name, array $attributes = []): self;

    /**
     * Record an exception on the span.
     */
    public function recordException(\Throwable $exception): self;

    /**
     * Set the span status (ok, error, unset).
     */
    public function setStatus(string $status, ?string $message = null): self;

    /**
     * End the span (records end time).
     */
    public function end(): void;

    /**
     * Get the span context (trace_id, span_id) for propagation.
     */
    public function context(): SpanContext;
}