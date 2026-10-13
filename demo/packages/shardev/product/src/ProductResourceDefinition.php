<?php

declare(strict_types=1);

namespace Shardevcom\Product;

use Shardevcom\Resources\QueryOptions;
use Shardevcom\Resources\QueryResult;
use Shardevcom\Resources\ResourceDefinition;
use Shardevcom\Resources\FilterSet;
use Shardevcom\Resources\IncludeSet;
use Shardevcom\Resources\SortSet;
use Shardevcom\Resources\Pagination;
use Shardevcom\Resources\FieldSet;

/**
 * Product resource definition (T-022).
 *
 * Defines the product resource with its allowed filters, includes, sorts, and fields.
 */
final class ProductResourceDefinition
{
    public static function definition(): ResourceDefinition
    {
        return ResourceDefinition::make('products')
            ->withActions(['view', 'create', 'update', 'delete', 'list', 'import', 'export'])
            ->withFilters(['sku', 'ean', 'brand_id', 'active'])
            ->withIncludes(['brand'])
            ->withSorting(['id', 'sku', 'name', 'created_at', 'updated_at'])
            ->withSearchable(['sku', 'name'])
            ->withFields(['id', 'sku', 'ean', 'name', 'brand_id', 'active', 'created_at', 'updated_at'])
            ->withSelectFields(['id', 'sku', 'name'])
            ->withRepository(\Shardevcom\Product\Repositories\ProductRepository::class)
            ->withModel(\Shardevcom\Product\Models\Product::class);
    }
}