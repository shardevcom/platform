<?php

declare(strict_types=1);

namespace Shardevcom\Core\Contracts;

/**
 * Propagation handle of a span (T-121).
 *
 * Enough identifiers for a backend to correlate events, without tying the
 * contract to a specific tracing format.
 */
interface SpanContext
{
    public function traceId(): string;

    public function spanId(): string;

    public function isSampled(): bool;
}