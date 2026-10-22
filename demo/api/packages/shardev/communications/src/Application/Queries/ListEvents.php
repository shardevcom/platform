<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Application\Queries;

use Shardevcom\Communications\Contracts\EventConfiguration;
use Shardevcom\Communications\Domain\Data\EventDefinition;

final class ListEvents
{
    public function __construct(private readonly EventConfiguration $events)
    {
    }

    /**
     * @return list<array{id: int, key: string, name: string, description: ?string, category: string, active: bool, channels: list<string>}>
     */
    public function execute(): array
    {
        return array_map(
            static fn (EventDefinition $event): array => [
                'id' => $event->id,
                'key' => $event->key,
                'name' => $event->name,
                'description' => $event->description,
                'category' => $event->category->value,
                'active' => $event->active,
                'channels' => array_map(static fn ($channel): string => $channel->value, $event->channels),
            ],
            $this->events->all(),
        );
    }
}
