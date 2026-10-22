<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Domain\Data;

use Shardevcom\Communications\Domain\Enums\Channel;
use Shardevcom\Communications\Domain\Enums\MessageStatus;

final class DispatchOutcome
{
    public function __construct(
        public readonly Channel $channel,
        public readonly MessageStatus $status,
        public readonly ?int $messageId = null,
        public readonly ?string $detail = null,
    ) {
    }

    /**
     * @return array{channel: string, status: string, message_id: ?int, detail: ?string}
     */
    public function toArray(): array
    {
        return [
            'channel' => $this->channel->value,
            'status' => $this->status->value,
            'message_id' => $this->messageId,
            'detail' => $this->detail,
        ];
    }
}
