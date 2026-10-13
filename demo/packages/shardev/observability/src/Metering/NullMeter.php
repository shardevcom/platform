<?php

declare(strict_types=1);

namespace Shardevcom\Observability\Metering;

use Shardevcom\Core\Contracts\Meter;

/**
 * Metering adapter for "no metrics backend configured".
 *
 * T-CASA-023 keeps the contract honest: Meter EXISTS as an interface, and this
 * implementation is the one chosen when the platform is installed without a
 * collector (Prometheus/StatsD/DataDog). It still executes the instrumented
 * code path (time() runs the callable) so wiring a real backend later is a
 * binding swap, not a code audit.
 */
final class NullMeter implements Meter
{
    public function counter(string $name, array $tags = []): void
    {
    }

    public function gauge(string $name, float $value, array $tags = []): void
    {
    }

    public function histogram(string $name, float $value, array $tags = []): void
    {
    }

    public function time(string $name, callable $callable, array $tags = []): mixed
    {
        return $callable();
    }

    public function withTags(array $defaultTags): Meter
    {
        return $this;
    }
}