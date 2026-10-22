<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Domain\Data;

use DateTimeImmutable;
use Shardevcom\Communications\Domain\Enums\Channel;
use Shardevcom\Communications\Domain\Enums\MessageStatus;

/**
 * Allow-listed filters for the delivery history. Nothing from the request is
 * passed through to the query builder verbatim.
 */
final class MessageHistoryFilter
{
    public function __construct(
        public readonly ?string $eventKey = null,
        public readonly ?Channel $channel = null,
        public readonly ?MessageStatus $status = null,
        public readonly ?string $recipientType = null,
        public readonly ?string $recipientId = null,
        public readonly ?string $recipientGuard = null,
        public readonly ?DateTimeImmutable $from = null,
        public readonly ?DateTimeImmutable $to = null,
    ) {
    }
}
