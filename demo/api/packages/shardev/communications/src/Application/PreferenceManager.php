<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Application;

use Shardevcom\Communications\Contracts\PreferenceStore;
use Shardevcom\Communications\Domain\Enums\Channel;

/**
 * Application-level opt-in/opt-out API.
 *
 * Passing null for channel/event means "all", which is how a user disables
 * every communication. The dispatcher always consults the store directly so
 * this class cannot become a second opt-out decision point.
 */
final class PreferenceManager
{
    public function __construct(private readonly PreferenceStore $preferences)
    {
    }

    public function isOptedOut(
        string $guard,
        string $userId,
        Channel $channel,
        ?string $eventKey = null,
    ): bool {
        return $this->preferences->isOptedOut($guard, $userId, $channel, $eventKey ?? PreferenceStore::ALL);
    }

    public function optOut(
        string $guard,
        string $userId,
        ?Channel $channel = null,
        ?string $eventKey = null,
        ?string $reason = null,
    ): void {
        $this->preferences->optOut($guard, $userId, $channel, $eventKey, $reason);
    }

    public function optIn(
        string $guard,
        string $userId,
        ?Channel $channel = null,
        ?string $eventKey = null,
    ): void {
        $this->preferences->optIn($guard, $userId, $channel, $eventKey);
    }
}
