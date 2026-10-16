<?php

declare(strict_types=1);

namespace Shardevcom\Core\Contracts;

use Shardevcom\Core\ApplicationContext;
use Shardevcom\Resources\QueryOptions;
use Shardevcom\Resources\QueryResult;

/**
 * Boundary: translation of validated QueryOptions into result sets.
 * Callers express WHAT (filters/includes/sort/pagination already validated
 * against the ResourceDefinition); the executor decides HOW (Eloquent,
 * cache decorator, external service) without leaking query details upward.
 */
interface QueryExecutor
{
    public function execute(QueryOptions $options, ApplicationContext $context): QueryResult;
}
