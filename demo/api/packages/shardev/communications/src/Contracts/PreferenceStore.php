<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Contracts;

use Shardevcom\Communications\Domain\Enums\Channel;

/**
 * Opt-out boundary.
 *
 * A null channel means "all channels"; a null event means "all events".
 * The implementation stores them as '*' sentinels so the unique constraint
 * also works on SQLite.
 */
interface PreferenceStore
{
    public const ALL = '*';

    public function isOptedOut(string $guard, string $userId, Channel $channel, string $eventKey): bool;

    /**
     * @return list<array{channel: string, event_key: string, reason: ?string, opted_out_at: ?string}>
     */
    public function listFor(string $guard, string $userId): array;

    public function optOut(
        string $guard,
        string $userId,
        ?Channel $channel = null,
        ?string $eventKey = null,
        ?string $reason = null,
    ): void;

    public function optIn(
        string $guard,
        string $userId,
        ?Channel $channel = null,
        ?string $eventKey = null,
    ): void;
}
