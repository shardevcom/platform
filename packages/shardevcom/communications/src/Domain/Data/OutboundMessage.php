<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Domain\Data;

use Shardevcom\Communications\Domain\Enums\Channel;
use Shardevcom\Communications\Domain\Enums\TemplateFormat;

/**
 * Message handed to a channel transport.
 */
final class OutboundMessage
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly Channel $channel,
        public readonly string $recipientType,
        public readonly ?string $recipientId,
        public readonly ?string $recipientGuard,
        public readonly ?string $address,
        public readonly TemplateFormat $format,
        public readonly ?string $subject,
        public readonly string $body,
        public readonly array $metadata = [],
        public readonly ?string $eventKey = null,
    ) {
    }
}
