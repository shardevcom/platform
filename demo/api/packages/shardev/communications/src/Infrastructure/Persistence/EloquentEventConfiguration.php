<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Shardevcom\Communications\Contracts\EventConfiguration;
use Shardevcom\Communications\Domain\Data\EventDefinition;
use Shardevcom\Communications\Domain\Enums\Channel;
use Shardevcom\Communications\Domain\Enums\EventCategory;
use Shardevcom\Core\Exceptions\ResourceNotFound;

final class EloquentEventConfiguration implements EventConfiguration
{
    public function find(string $eventKey): ?EventDefinition
    {
        $event = CommunicationEvent::query()
            ->with(['channels' => static fn ($query) => $query->where('enabled', true)])
            ->where('key', $eventKey)
            ->where('active', true)
            ->first();

        return $event === null ? null : $this->toDefinition($event);
    }

    public function all(): array
    {
        return CommunicationEvent::query()
            ->with(['channels' => static fn ($query) => $query->where('enabled', true)])
            ->orderBy('key')
            ->get()
            ->map(fn (CommunicationEvent $event): EventDefinition => $this->toDefinition($event))
            ->all();
    }

    public function setChannelEnabled(string $eventKey, Channel $channel, bool $enabled): void
    {
        $event = CommunicationEvent::query()->where('key', $eventKey)->first();

        if ($event === null) {
            throw ResourceNotFound::for('communication_events', $eventKey);
        }

        CommunicationEventChannel::query()->updateOrCreate(
            [
                'event_id' => $event->getKey(),
                'channel' => $channel->value,
            ],
            ['enabled' => $enabled],
        );
    }

    public function save(
        string $key,
        string $name,
        ?string $description,
        EventCategory $category,
        bool $active,
        array $channels,
    ): EventDefinition {
        $enabled = [];

        foreach ($channels as $channel) {
            if ($channel instanceof Channel) {
                $enabled[$channel->value] = $channel;
            }
        }

        return DB::transaction(function () use ($key, $name, $description, $category, $active, $enabled): EventDefinition {
            $event = CommunicationEvent::query()->updateOrCreate(
                ['key' => $key],
                [
                    'name' => $name,
                    'description' => $description,
                    'category' => $category->value,
                    'active' => $active,
                ],
            );

            foreach (Channel::cases() as $channel) {
                CommunicationEventChannel::query()->updateOrCreate(
                    [
                        'event_id' => $event->getKey(),
                        'channel' => $channel->value,
                    ],
                    ['enabled' => isset($enabled[$channel->value])],
                );
            }

            $event->load(['channels' => static fn ($query) => $query->where('enabled', true)]);

            return $this->toDefinition($event);
        });
    }

    private function toDefinition(CommunicationEvent $event): EventDefinition
    {
        $channels = [];

        foreach ($event->channels as $row) {
            $channel = $row->channel instanceof Channel
                ? $row->channel
                : Channel::tryFrom((string) $row->channel);

            if ($channel !== null) {
                $channels[] = $channel;
            }
        }

        return new EventDefinition(
            (int) $event->getKey(),
            (string) $event->key,
            (string) $event->name,
            $event->category,
            $channels,
            (bool) $event->active,
            $event->description,
        );
    }
}
