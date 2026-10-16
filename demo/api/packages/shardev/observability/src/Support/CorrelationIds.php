<?php

declare(strict_types=1);

namespace Shardevcom\Observability\Support;

/**
 * T-CASA-023: correlation/request identifier rules, pure input/output.
 *
 * An incoming correlation id is ONLY trusted when it is a well-formed UUIDv4;
 * any other value is rejected and a fresh one is generated instead. This is a
 * security posture, not a preference: arbitrary client strings land verbatim
 * in log lines, so accepting them would let a caller forge/fake trace chains —
 * and even embed text (injection into dashboards) — in the platform's own
 * logs.
 */
final class CorrelationIds
{
    /**
     * Accept an inbound id if and only if it is a valid UUIDv4; otherwise
     * return null so the caller generates its own.
     */
    public static function accept(?string $inbound): ?string
    {
        if ($inbound === null || $inbound === '') {
            return null;
        }

        return self::isUuid($inbound) ? $inbound : null;
    }

    /**
     * A fresh, framework-free UUIDv4 (RFC 4122): 122 random bits in the
     * standard 8-4-4-4-12 shape with version/variant bits set.
     */
    public static function generate(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(substr($bytes, 0, 4)),
            bin2hex(substr($bytes, 4, 2)),
            bin2hex(substr($bytes, 6, 2)),
            bin2hex(substr($bytes, 8, 2)),
            bin2hex(substr($bytes, 10, 6))
        );
    }

    public static function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
    }
}