<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor;

/**
 * Severity levels for security findings.
 */
enum SecuritySeverity: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Info = 'info';

    public function priority(): int
    {
        return match ($this) {
            self::Critical => 5,
            self::High => 4,
            self::Medium => 3,
            self::Low => 2,
            self::Info => 1,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Critical => 'red',
            self::High => 'red',
            self::Medium => 'yellow',
            self::Low => 'blue',
            self::Info => 'cyan',
        };
    }
}