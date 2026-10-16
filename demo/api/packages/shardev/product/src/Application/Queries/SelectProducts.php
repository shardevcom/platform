<?php

declare(strict_types=1);

namespace Shardevcom\Product\Application\Queries;

use Shardevcom\Core\ApplicationContext;
use Shardevcom\Product\Repositories\ProductRepository;
use Shardevcom\Resources\QueryOptions;
use Shardevcom\Resources\QueryResult;

/**
 * Select products for dropdowns/autocomplete (T-XXX).
 *
 * Returns only selectFields (id, sku, name) configured in the resource definition.
 * Requires 'products.list' permission.
 */
final class SelectProducts
{
    public function __construct(private \Shardevcom\Product\Repositories\ProductRepository $products)
    {
    }

    /**
     * Raw input is validated here (once) and nowhere else.
     *
     * @param array{filters?: array<string, mixed>, includes?: string|list<string>, sort?: list<string>, page?: int, per_page?: int, fields?: list<string>, search?: ?string} $query
     * @return \Shardevcom\Resources\QueryResult
     */
    public function execute(array $query, \Shardevcom\Core\ApplicationContext $context): \Shardevcom\Resources\QueryResult
    {
        $query['action'] = 'list';

        return $this->products->list(
            \Shardevcom\Resources\QueryOptions::create($this->products->resourceDefinition(), $query),
            $context,
        );
    }
}