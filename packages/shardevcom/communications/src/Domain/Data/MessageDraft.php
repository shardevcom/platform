<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Domain\Data;

use Shardevcom\Communications\Domain\Enums\Channel;
use Shardevcom\Communications\Domain\Enums\TemplateFormat;

/**
 * Fully resolved message ready to be persisted as QUEUED.
 */
final class MessageDraft
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $eventKey,
        public readonly int $eventId,
        public readonly ?int $templateId,
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
    ) {
    }
}
