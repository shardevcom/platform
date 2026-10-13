<?php

declare(strict_types=1);

namespace Shardevcom\Product\Application\Queries;

use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\Exceptions\ResourceNotFound;
use Shardevcom\Product\Repositories\ProductRepository;

/**
 * Fetch one product (T-060).
 *
 * Uses findOrFail so an absent row and a row hidden by the tenant scope are
 * reported identically (ResourceNotFound). That indistinguishability is
 * deliberate: a caller must not be able to probe which ids exist in another
 * tenant.
 */
final class GetProduct
{
    public function __construct(private \Shardevcom\Product\Repositories\ProductRepository $products)
    {
    }

    /**
     * @param list<string> $includes relations to eager-load. They are validated
     *                                  against the resource definition by the
     *                                  repository, so an adapter cannot ask for
     *                                  a relationship the resource does not
     *                                  declare.
     * @return array<string, mixed>
     */
    public function execute(string|int $id, \Shardevcom\Core\ApplicationContext $context, array $includes = []): array
    {
        return $this->products->findOrFail($id, $context, $includes);
    }
}