<?php

declare(strict_types=1);

namespace Shardevcom\Observability\Logging;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

/**
 * Single-line JSON formatter for the T-CASA-023 structured log contract.
 *
 * The key order is fixed (timestamp, level, message, then the platform
 * dimensions) so a dashboard template can rely on it; extra context keys are
 * appended afterwards. Unknown/scalar-mixed context values are cast to
 * strings so a bad context entry can never produce a non-JSON line.
 */
final class JsonFormatter implements FormatterInterface
{
    /**
     * Ordered platform dimensions; only non-null values are emitted.
     *
     * @var list<string>
     */
    private const DIMENSIONS = [
        'correlation_id',
        'request_id',
        'user_id',
        'tenant_id',
        'guard',
        'action',
        'resource',
        'ip',
        'ua',
        'duration_ms',
        'status',
    ];

    public function format(LogRecord $record): string
    {
        $context = array_filter(
            (array) $record->context,
            static fn (mixed $value): bool => $value !== null && $value !== ''
        );

        $line = [
            'timestamp' => $record->datetime->format('c'),
            'level' => $record->level->getName(),
            'message' => $record->message,
        ];

        foreach (self::DIMENSIONS as $key) {
            if (array_key_exists($key, $context) && $context[$key] !== null && $context[$key] !== '') {
                $line[$key] = $context[$key];
            }
        }

        foreach ($context as $key => $value) {
            if (array_key_exists($key, $line) || $value === null || $value === '') {
                continue;
            }

            $line[$key] = $value;
        }

        $encoded = json_encode($line, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return ($encoded === false ? '{}' : $encoded)."\n";
    }

    public function formatBatch(array $records): string
    {
        return implode('', array_map($this->format(...), $records));
    }
}