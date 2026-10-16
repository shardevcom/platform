<?php

declare(strict_types=1);

namespace Tests\Unit\Product;

use Shardevcom\Product\ProductResourceDefinition;
use PHPUnit\Framework\TestCase;

/**
 * T-022 acceptance: the demo resource exposes exactly the declared
 * allow-lists (used later by T-040 query validation).
 */
final class ProductResourceDefinitionTest extends TestCase
{
    public function test_product_filters_match_the_spec(): void
    {
        $definition = ProductResourceDefinition::definition();

        $this->assertSame('products', $definition->name());
        $this->assertSame(['sku', 'ean', 'brand_id', 'active'], $definition->filters());
    }

    public function test_undeclared_includes_are_rejected(): void
    {
        $definition = ProductResourceDefinition::definition();

        $this->assertTrue($definition->allowsInclude('brand'));
        $this->assertFalse($definition->allowsInclude('images'));
        $this->assertFalse($definition->allowsInclude('catalog'));
    }
}
