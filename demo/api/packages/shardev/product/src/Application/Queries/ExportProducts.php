<?php

declare(strict_types=1);

namespace Shardevcom\Product\Application\Queries;

use Shardevcom\Core\ApplicationContext;
use Shardevcom\Product\Exports\ProductsExport;
use Shardevcom\Product\Repositories\ProductRepository;
use Shardevcom\Resources\QueryOptions;
use Shardevcom\Resources\QueryResult;

/**
 * Export products (T-CASA-015).
 *
 * Requires 'products.export' permission. The gate is the lifecycle funnel:
 * a role holding only products.view cannot bulk-extract through this path,
 * because export authorizes products.export on the same repository operation.
 *
 * Export is a bulk extract, not a page, so the result set is never
 * paginated; the adapter decides how to materialize it (file, stream).
 */
final class ExportProducts
{
    public function __construct(private ProductRepository $products)
    {
    }

    /**
     * @param array{filters?: array<string, mixed>, includes?: string|list<string>, sort?: list<string>, fields?: list<string>, search?: ?string, format?: string} $query
     */
    public function execute(array $query, ApplicationContext $context): ProductsExport
    {
        $query['action'] = 'export';
        $query['pagination'] = false;
        $format = $query['format'] ?? 'xlsx';

        $result = $this->products->export(
            QueryOptions::create($this->products->resourceDefinition(), $query),
            $context,
        );

        return new ProductsExport($result->items(), $format);
    }
}