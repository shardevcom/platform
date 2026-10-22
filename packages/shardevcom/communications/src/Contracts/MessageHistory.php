<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Contracts;

use Shardevcom\Communications\Domain\Data\MessageHistoryFilter;
use Shardevcom\Communications\Domain\Data\MessageHistoryPage;

interface MessageHistory
{
    public function paginate(MessageHistoryFilter $filter, int $page, int $perPage): MessageHistoryPage;
}
