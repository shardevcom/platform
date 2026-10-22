<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Domain\Data;

use Shardevcom\Core\Results\PaginationMeta;

final class MessageHistoryPage
{
    /**
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly PaginationMeta $pagination,
    ) {
    }
}
