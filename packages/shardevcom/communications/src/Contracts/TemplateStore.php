<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Contracts;

use Shardevcom\Communications\Domain\Data\MessageTemplate;
use Shardevcom\Communications\Domain\Enums\Channel;
use Shardevcom\Communications\Domain\Enums\TemplateFormat;

interface TemplateStore
{
    public function find(int $eventId, Channel $channel, string $locale): ?MessageTemplate;

    /**
     * @return list<MessageTemplate>
     */
    public function forEvent(int $eventId, bool $activeOnly = false): array;

    /**
     * Creates or updates the template for (event, channel, locale).
     *
     * @param array<string, mixed> $metadata
     */
    public function upsert(
        int $eventId,
        Channel $channel,
        string $locale,
        ?string $subject,
        string $body,
        TemplateFormat $format = TemplateFormat::HTML,
        array $metadata = [],
        bool $active = true,
    ): MessageTemplate;
}
