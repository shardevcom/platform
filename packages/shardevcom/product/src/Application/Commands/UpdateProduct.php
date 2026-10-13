<?php

declare(strict_types=1);

namespace Shardevcom\Product\Application\Commands;

use Shardevcom\Core\ApplicationContext;
use Shardevcom\Product\Application\Data\UpdateProductData;
use Shardevcom\Product\Repositories\ProductRepository;

/**
 * Update a product (T-060).
 *
 * The only place product update rules live. It does not know whether it was
 * triggered by an HTTP PATCH, a GraphQL mutation or a console command, which
 * is what makes REST and GraphQL parity a property of the design instead of a
 * coincidence someone has to maintain.
 *
 * Everything below the DTO belongs to other layers and is never duplicated
 * here: validation → UpdateProductData (T-040 allow-lists + DTO rules),
 * authorization → the repository through the lifecycle, persistence → the
 * adapter.
 */
final class UpdateProduct
{
    public function __construct(private ProductRepository $products)
    {
    }

    /**
     * Accepts an already-built DTO or the raw payload to validate. A controller
     * may pass the DTO it built from a form request; a GraphQL resolver or a
     * console command may pass the raw array and let the DTO validate it.
     *
     * @param UpdateProductData|array<string, mixed> $input
     * @return array<string, mixed> the stored row
     */
    public function execute(string|int $id, UpdateProductData|array $input, \Shardevcom\Core\ApplicationContext $context): array
    {
        $data = $input instanceof \Shardevcom\Product\Application\Data\UpdateProductData
            ? $input
            : \Shardevcom\Product\Application\Data\UpdateProductData::fromArray($input);

        return $this->products->update($id, $data->toAttributes(), $context);
    }
}