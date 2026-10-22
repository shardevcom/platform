<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Domain\Data;

use Shardevcom\Communications\Domain\Enums\Channel;
use Shardevcom\Communications\Domain\Enums\TemplateFormat;

/**
 * Persisted message row consumed by the delivery worker.
 */
final class PendingMessage
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly int $id,
        public readonly string $uuid,
        public readonly string $eventKey,
        public readonly Channel $channel,
        public readonly string $recipientType,
        public readonly ?string $recipientId,
        public readonly ?string $recipientGuard,
        public readonly ?string $recipientAddress,
        public readonly string $locale,
        public readonly TemplateFormat $format,
        public readonly ?string $subject,
        public readonly string $body,
        public readonly array $payload,
        public readonly array $metadata,
        public readonly int $attempts,
    ) {
    }

    public function toOutboundMessage(): OutboundMessage
    {
        return new OutboundMessage(
            $this->channel,
            $this->recipientType,
            $this->recipientId,
            $this->recipientGuard,
            $this->recipientAddress,
            $this->format,
            $this->subject,
            $this->body,
            $this->metadata,
            $this->eventKey,
        );
    }
}
