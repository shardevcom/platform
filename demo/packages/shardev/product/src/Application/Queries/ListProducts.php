<?php

declare(strict_types=1);

namespace Shardevcom\Product\Application\Queries;

use Shardevcom\Core\ApplicationContext;
use Shardevcom\Product\Repositories\ProductRepository;
use Shardevcom\Resources\QueryOptions;
use Shardevcom\Resources\QueryResult;

/**
 * List products (T-060).
 *
 * Orchestration only: the repository owns authorization, the tenant scope and
 * the lifecycle; QueryOptions (T-040) owns the allow-lists. A REST controller
 * and a GraphQL resolver both land here, so "list products" cannot mean two
 * different things depending on the transport.
 */
final class ListProducts
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
        $query['action'] = 'view';

        return $this->products->query(
            \Shardevcom\Resources\QueryOptions::create($this->products->resourceDefinition(), $query),
            $context,
        );
    }
}