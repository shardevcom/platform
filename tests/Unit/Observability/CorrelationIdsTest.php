<?php

declare(strict_types=1);

namespace Tests\Unit\Observability;

use PHPUnit\Framework\TestCase;
use Shardevcom\Observability\Support\CorrelationIds;

/**
 * T-CASA-023: inbound correlation ids are trusted only as UUIDv4; anything
 * else is rejected so a fresh value is generated.
 */
final class CorrelationIdsTest extends TestCase
{
    public function test_null_and_empty_are_rejected(): void
    {
        $this->assertNull(CorrelationIds::accept(null));
        $this->assertNull(CorrelationIds::accept(''));
    }

    public function test_uuidv4_is_accepted(): void
    {
        $uuid = '11111111-1111-4111-8111-111111111111';

        $this->assertSame($uuid, CorrelationIds::accept($uuid));
    }

    public function test_non_uuid_and_other_uuid_versions_are_rejected(): void
    {
        $this->assertNull(CorrelationIds::accept('not-a-uuid'));
        $this->assertNull(CorrelationIds::accept('11111111-1111-2111-8111-111111111111'));
        $this->assertNull(CorrelationIds::accept('11111111-1111-4111-1111-111111111111'));
        $this->assertNull(CorrelationIds::accept('javascript:alert(1)'));
    }

    public function test_generated_ids_are_uuidv4_and_unique(): void
    {
        $first = CorrelationIds::generate();

        $this->assertTrue(CorrelationIds::isUuid($first));
        $this->assertNotSame($first, CorrelationIds::generate());
        $this->assertNotSame($first, CorrelationIds::generate());
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $first
        );
    }
}