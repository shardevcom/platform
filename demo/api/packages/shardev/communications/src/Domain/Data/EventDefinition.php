<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Domain\Data;

use Shardevcom\Communications\Domain\Enums\Channel;
use Shardevcom\Communications\Domain\Enums\EventCategory;

/**
 * A configured event and the channels currently enabled for it.
 */
final class EventDefinition
{
    /**
     * @param list<Channel> $channels
     */
    public function __construct(
        public readonly int $id,
        public readonly string $key,
        public readonly string $name,
        public readonly EventCategory $category,
        public readonly array $channels,
        public readonly bool $active = true,
        public readonly ?string $description = null,
    ) {
    }

    public function hasChannel(Channel $channel): bool
    {
        return in_array($channel, $this->channels, true);
    }

    /**
     * @return list<string>
     */
    public function channelKeys(): array
    {
        return array_map(static fn (Channel $channel): string => $channel->value, $this->channels);
    }
}
