<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Domain\Data;

use Shardevcom\Communications\Domain\Enums\Channel;
use Shardevcom\Communications\Domain\Enums\TemplateFormat;

/**
 * Channel-specific template for one event and locale.
 *
 * `metadata` carries channel extras such as links, attachments or provider
 * options. It is intentionally not interpreted by the dispatcher: transports
 * receive it verbatim.
 */
final class MessageTemplate
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly int $id,
        public readonly int $eventId,
        public readonly Channel $channel,
        public readonly string $locale,
        public readonly TemplateFormat $format,
        public readonly ?string $subject,
        public readonly string $body,
        public readonly array $metadata,
        public readonly bool $active = true,
    ) {
    }
}
