<?php

declare(strict_types=1);

namespace Tests\Unit\Observability;

use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Shardevcom\Observability\Logging\JsonFormatter;

final class JsonFormatterTest extends TestCase
{
    public function test_line_is_single_json_with_ordered_platform_dimensions(): void
    {
        $formatter = new JsonFormatter();

        $line = $formatter->format(new LogRecord(
            datetime: new \DateTimeImmutable('2026-10-07T10:00:00+00:00'),
            channel: 'structured',
            level: Level::Info,
            message: 'Resource action completed',
            context: [
                'correlation_id' => '11111111-1111-4111-8111-111111111111',
                'request_id' => '22222222-2222-4222-8222-222222222222',
                'user_id' => '7',
                'tenant_id' => 'A',
                'guard' => 'admin',
                'action' => 'list',
                'resource' => 'products',
                'ip' => '127.0.0.1',
                'ua' => 'php',
                'duration_ms' => 12.5,
                'status' => 200,
            ],
        ));

        $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame([
            'timestamp',
            'level',
            'message',
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
        ], array_keys($decoded));

        $this->assertSame('2026-10-07T10:00:00+00:00', $decoded['timestamp']);
        $this->assertSame('INFO', $decoded['level']);
        $this->assertSame('Resource action completed', $decoded['message']);
        $this->assertSame(200, $decoded['status']);
    }

    public function test_null_dimensions_are_omitted_but_extra_context_is_appended(): void
    {
        $line = (new JsonFormatter())->format(new LogRecord(
            datetime: new \DateTimeImmutable('2026-10-07T10:00:00+00:00'),
            channel: 'structured',
            level: Level::Error,
            message: 'HTTP request failed',
            context: ['correlation_id' => null, 'path' => 'admin/api/v1/products'],
        ));

        $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

        $this->assertArrayNotHasKey('correlation_id', $decoded);
        $this->assertSame('admin/api/v1/products', $decoded['path']);
    }

    public function test_format_batch_emits_one_line_per_record(): void
    {
        $formatter = new JsonFormatter();
        $record = new LogRecord(
            datetime: new \DateTimeImmutable('2026-10-07T10:00:00+00:00'),
            channel: 'structured',
            level: Level::Info,
            message: 'm',
        );

        $batch = $formatter->formatBatch([$record, $record]);

        $this->assertSame(2, substr_count($batch, "\n"));
    }
}