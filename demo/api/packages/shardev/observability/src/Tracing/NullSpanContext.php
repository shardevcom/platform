<?php

declare(strict_types=1);

namespace Shardevcom\Observability\Tracing;

use Shardevcom\Core\Contracts\SpanContext;

/**
 * No-op span context: never sampled, no ids to propagate.
 */
final class NullSpanContext implements SpanContext
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function traceId(): string
    {
        return '';
    }

    public function spanId(): string
    {
        return '';
    }

    public function isSampled(): bool
    {
        return false;
    }
}