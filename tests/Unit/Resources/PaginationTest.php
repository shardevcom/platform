<?php

declare(strict_types=1);

namespace Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;
use Shardevcom\Resources\Pagination;

/**
 * T-CASA-015: bulk reads (export) must not be silently bounded by the page
 * defaults, while every interactive listing keeps its pagination buckets.
 */
final class PaginationTest extends TestCase
{
    public function test_unlimited_pagination_skips_the_page_bounds(): void
    {
        $unlimited = Pagination::unlimited();

        $this->assertTrue($unlimited->isUnlimited());
        $this->assertNull($unlimited->page);
        $this->assertNull($unlimited->perPage);
    }

    public function test_capping_is_a_no_op_for_unlimited_pagination(): void
    {
        $capped = Pagination::unlimited()->capped(50);

        $this->assertTrue($capped->isUnlimited());
        $this->assertNull($capped->page);
        $this->assertNull($capped->perPage);
    }

    public function test_regular_pagination_is_still_capped(): void
    {
        $paged = Pagination::make(1, 500)->capped(100);

        $this->assertFalse($paged->isUnlimited());
        $this->assertSame(100, $paged->perPage);
        $this->assertSame(1, $paged->page);
    }

    public function test_page_and_per_page_are_validated(): void
    {
        $this->expectException(\Shardevcom\Core\Exceptions\ValidationFailed::class);

        Pagination::make(0, 15);
    }
}