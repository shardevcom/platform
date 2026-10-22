<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Contracts;

use Shardevcom\Communications\Domain\Data\EventDefinition;
use Shardevcom\Communications\Domain\Enums\Channel;
use Shardevcom\Communications\Domain\Enums\EventCategory;

/**
 * Read/write boundary for the event -> channel matrix.
 */
interface EventConfiguration
{
    /**
     * Returns null when the event does not exist or is disabled.
     */
    public function find(string $eventKey): ?EventDefinition;

    /**
     * Every configured event, including disabled ones, for the admin surface.
     *
     * @return list<EventDefinition>
     */
    public function all(): array;

    /**
     * Enables/disables one channel for an event without touching code.
     * Unknown events are reported as ResourceNotFound.
     */
    public function setChannelEnabled(string $eventKey, Channel $channel, bool $enabled): void;

    /**
     * Creates or updates an event and replaces its channel matrix.
     *
     * @param list<Channel> $channels enabled channels
     */
    public function save(
        string $key,
        string $name,
        ?string $description,
        EventCategory $category,
        bool $active,
        array $channels,
    ): EventDefinition;
}
