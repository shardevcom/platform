<?php

declare(strict_types=1);

namespace Shardevcom\Product\Application\Commands;

use Shardevcom\Core\ApplicationContext;
use Shardevcom\Product\Application\Data\CreateProductData;
use Shardevcom\Product\Repositories\ProductRepository;

/**
 * Create a product (T-060).
 *
 * The only place product creation rules live. It does not know whether it was
 * triggered by an HTTP POST, a GraphQL mutation or a console command, which
 * is what makes REST and GraphQL parity a property of the design instead of a
 * coincidence someone has to maintain.
 *
 * Everything below the DTO belongs to other layers and is never duplicated
 * here: validation → CreateProductData (T-040 allow-lists + DTO rules),
 * authorization → the repository through the lifecycle, persistence → the
 * adapter.
 */
final class CreateProduct
{
    public function __construct(private ProductRepository $products)
    {
    }

    /**
     * Accepts an already-built DTO or the raw payload to validate. A controller
     * may pass the DTO it built from a form request; a GraphQL resolver or a
     * console command may pass the raw array and let the DTO validate it.
     *
     * @param CreateProductData|array<string, mixed> $input
     * @return array<string, mixed> the stored row
     */
    public function execute(CreateProductData|array $input, \Shardevcom\Core\ApplicationContext $context): array
    {
        $data = $input instanceof \Shardevcom\Product\Application\Data\CreateProductData
            ? $input
            : \Shardevcom\Product\Application\Data\CreateProductData::fromArray($input);

        return $this->products->create($data->toAttributes(), $context);
    }
}