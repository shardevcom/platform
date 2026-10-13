<?php

declare(strict_types=1);

namespace Shardevcom\Observability\Tracing;

use Shardevcom\Core\Contracts\Span;
use Shardevcom\Core\Contracts\SpanContext;
use Throwable;

/**
 * No-op span returned by NullTracer. Mutable on purpose: instrumented code
 * may set attributes/status; nothing observes them.
 */
final class NullSpan implements Span
{
    public function setAttribute(string $key, mixed $value): Span
    {
        return $this;
    }

    public function addEvent(string $name, array $attributes = []): Span
    {
        return $this;
    }

    public function recordException(Throwable $exception): Span
    {
        return $this;
    }

    public function setStatus(string $status, ?string $message = null): Span
    {
        return $this;
    }

    public function end(): void
    {
    }

    public function context(): SpanContext
    {
        return NullSpanContext::instance();
    }
}