<?php

declare(strict_types=1);

namespace Shardevcom\Core\Results;

/**
 * Pagination metadata (T-014).
 *
 * lastPage follows the paginator convention: at least 1, even empty.
 */
final class PaginationMeta
{
    public function __construct(
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $total,
    ) {
    }

    public static function unlimited(int $total): self
    {
        return new self(1, max(1, $total), $total);
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    /**
     * @return array{page: int, per_page: int, total: int, last_page: int}
     */
    public function toArray(): array
    {
        return [
            'page' => $this->page,
            'per_page' => $this->perPage,
            'total' => $this->total,
            'last_page' => $this->lastPage(),
        ];
    }
}
