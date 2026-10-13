<?php

declare(strict_types=1);

namespace Shardevcom\Product\Application\Commands;

use Shardevcom\Core\ApplicationContext;
use Shardevcom\Product\Repositories\ProductRepository;

/**
 * Delete a product (T-060).
 *
 * The only place product deletion rules live. It does not know whether it was
 * triggered by an HTTP DELETE, a GraphQL mutation or a console command, which
 * is what makes REST and GraphQL parity a property of the design instead of a
 * coincidence someone has to maintain.
 *
 * Everything below the DTO belongs to other layers and is never duplicated
 * here: authorization → the repository through the lifecycle, persistence → the
 * adapter.
 */
final class DeleteProduct
{
    public function __construct(private \Shardevcom\Product\Repositories\ProductRepository $products)
    {
    }

    /**
     * @return void
     */
    public function execute(string|int $id, \Shardevcom\Core\ApplicationContext $context): void
    {
        $this->products->delete($id, $context);
    }
}