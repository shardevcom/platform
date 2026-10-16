<?php

declare(strict_types=1);

namespace Shardevcom\Observability\Tracing;

use Shardevcom\Core\Contracts\Span;
use Shardevcom\Core\Contracts\Tracer;

/**
 * Tracing adapter for "no tracing backend configured".
 *
 * T-CASA-023 only ships OTel wires when there is a backend to consume them;
 * without one, inSpan() still executes the callable so the decorated business
 * code path is exercised, and startSpan() returns a no-op Span that obeys the
 * same contract end/attribute/event lifecycle a real tracer would drive.
 */
final class NullTracer implements Tracer
{
    public function startSpan(string $name, array $attributes = []): Span
    {
        return new NullSpan();
    }

    public function currentSpan(): ?Span
    {
        return null;
    }

    public function inSpan(string $name, callable $callable, array $attributes = []): mixed
    {
        return $callable();
    }
}