<?php

declare(strict_types=1);

namespace Shardevcom\Product\Repositories;

use Shardevcom\Core\Contracts\QueryExecutor;
use Shardevcom\Core\Lifecycle\ActionLifecycle;
use Shardevcom\Core\Repositories\AbstractResourceRepository;
use Shardevcom\Persistence\EloquentResourcePersistence;
use Shardevcom\Product\ProductResourceDefinition;
use Shardevcom\Resources\ResourceDefinition;

/**
 * Product repository (T-053).
 *
 * Extends the base resource repository with Product-specific model.
 */
final class ProductRepository extends AbstractResourceRepository
{
    use EloquentResourcePersistence;

    public function __construct(ActionLifecycle $lifecycle, QueryExecutor $executor)
    {
        parent::__construct($lifecycle, $executor);
    }

    public function resourceName(): string
    {
        return 'products';
    }

    public function resourceDefinition(): ResourceDefinition
    {
        return ProductResourceDefinition::definition();
    }

}