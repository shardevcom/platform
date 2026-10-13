<?php

declare(strict_types=1);

namespace Shardevcom\Resources;

use Shardevcom\Core\Results\PaginationMeta;

/**
 * Executor output (T-040). Plain row arrays plus optional pagination —
 * no Eloquent, no HTTP, so both adapters consume the same shape.
 */
final class QueryResult
{
    /**
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        private array $items,
        private ?PaginationMeta $pagination = null,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function pagination(): ?PaginationMeta
    {
        return $this->pagination;
    }
}
