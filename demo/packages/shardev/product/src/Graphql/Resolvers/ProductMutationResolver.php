<?php

declare(strict_types=1);

namespace Shardevcom\Product\Graphql\Resolvers;

use Shardevcom\Application\ApplicationContext\HttpApplicationContextBuilder;
use Shardevcom\Product\Application\Commands\CreateProduct;
use Shardevcom\Product\Application\Commands\DeleteProduct;
use Shardevcom\Product\Application\Commands\UpdateProduct;
use Shardevcom\Product\Application\Data\CreateProductData;
use Shardevcom\Product\Application\Data\UpdateProductData;

/**
 * GraphQL mutation adapter for products (T-073).
 *
 * Same rule as the query side: args to DTO, DTO to use case, nothing else.
 */
final class ProductMutationResolver
{
    public function __construct(
        private readonly CreateProduct $createProduct,
        private readonly UpdateProduct $updateProduct,
        private readonly DeleteProduct $deleteProduct,
        private readonly HttpApplicationContextBuilder $contextBuilder,
    ) {
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function createProduct(mixed $root, array $args): array
    {
        $input = $args['input'] ?? [];

        return $this->createProduct->execute(
            CreateProductData::fromArray($input),
            $this->contextBuilder->fromRequest(request()),
        );
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function updateProduct(mixed $root, array $args): array
    {
        $input = $args['input'] ?? [];

        return $this->updateProduct->execute(
            $args['id'],
            UpdateProductData::fromArray($input),
            $this->contextBuilder->fromRequest(request()),
        );
    }

    /**
     * DeleteProduct returns void by design, so there is nothing to hand back and
     * nothing to read first.
     *
     * Reading the row before deleting (as this resolver used to) was not a
     * harmless convenience: the read went through the view action, so a token
     * holding only `products.delete` was refused with FORBIDDEN. REST DELETE
     * never reads, so that also meant GraphQL and REST disagreed on who may
     * delete.
     *
     * @param array<string, mixed> $args
     */
    public function deleteProduct(mixed $root, array $args): bool
    {
        $this->deleteProduct->execute($args['id'], $this->contextBuilder->fromRequest(request()));

        return true;
    }
}