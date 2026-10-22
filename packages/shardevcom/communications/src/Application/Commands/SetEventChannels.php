<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Application\Commands;

use Shardevcom\Communications\Application\Queries\ListEvents;
use Shardevcom\Communications\Contracts\EventConfiguration;
use Shardevcom\Communications\Domain\Data\EventDefinition;
use Shardevcom\Communications\Domain\Enums\Channel;
use Shardevcom\Core\Exceptions\ResourceNotFound;
use Shardevcom\Core\Exceptions\ValidationFailed;

/**
 * Replaces the enabled-channel list of an event. Unknown channel names are
 * rejected instead of silently ignored.
 */
final class SetEventChannels
{
    public function __construct(
        private readonly EventConfiguration $events,
        private readonly ListEvents $listEvents,
    ) {
    }

    /**
     * @param list<mixed> $channels
     * @return array{event: string, channels: list<string>}
     */
    public function execute(string $eventKey, array $channels): array
    {
        $event = $this->requireEvent($eventKey);
        $enabled = [];
        $errors = [];

        foreach ($channels as $index => $value) {
            if (! is_string($value)) {
                $errors["channels.{$index}"] = 'Each channel must be a string.';
                continue;
            }

            $channel = Channel::tryFrom($value);

            if ($channel === null) {
                $errors["channels.{$index}"] = "Unknown channel [{$value}].";
                continue;
            }

            $enabled[$channel->value] = $channel;
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        foreach (Channel::cases() as $channel) {
            $this->events->setChannelEnabled($eventKey, $channel, isset($enabled[$channel->value]));
        }

        foreach ($this->listEvents->execute() as $updated) {
            if ($updated['key'] === $eventKey) {
                return ['event' => $eventKey, 'channels' => $updated['channels']];
            }
        }

        throw ResourceNotFound::for('communication_events', $eventKey);
    }

    private function requireEvent(string $eventKey): EventDefinition
    {
        foreach ($this->events->all() as $event) {
            if ($event->key === $eventKey) {
                return $event;
            }
        }

        throw ResourceNotFound::for('communication_events', $eventKey);
    }
}
