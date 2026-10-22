<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Infrastructure\Persistence;

use Shardevcom\Communications\Contracts\PreferenceStore;
use Shardevcom\Communications\Domain\Enums\Channel;

final class EloquentPreferenceStore implements PreferenceStore
{
    public function isOptedOut(string $guard, string $userId, Channel $channel, string $eventKey): bool
    {
        return CommunicationPreference::query()
            ->where('guard', $guard)
            ->where('user_id', $userId)
            ->whereIn('channel', [$channel->value, self::ALL])
            ->whereIn('event_key', [$eventKey, self::ALL])
            ->exists();
    }

    public function listFor(string $guard, string $userId): array
    {
        return CommunicationPreference::query()
            ->where('guard', $guard)
            ->where('user_id', $userId)
            ->orderBy('channel')
            ->orderBy('event_key')
            ->get()
            ->map(static fn (CommunicationPreference $preference): array => [
                'channel' => (string) $preference->channel,
                'event_key' => (string) $preference->event_key,
                'reason' => $preference->reason,
                'opted_out_at' => $preference->opted_out_at?->toIso8601String(),
            ])
            ->all();
    }

    public function optOut(
        string $guard,
        string $userId,
        ?Channel $channel = null,
        ?string $eventKey = null,
        ?string $reason = null,
    ): void {
        CommunicationPreference::query()->updateOrCreate(
            [
                'guard' => $guard,
                'user_id' => $userId,
                'channel' => $channel?->value ?? self::ALL,
                'event_key' => $eventKey ?? self::ALL,
            ],
            [
                'reason' => $reason,
                'opted_out_at' => now(),
            ],
        );
    }

    public function optIn(
        string $guard,
        string $userId,
        ?Channel $channel = null,
        ?string $eventKey = null,
    ): void {
        CommunicationPreference::query()
            ->where('guard', $guard)
            ->where('user_id', $userId)
            ->where('channel', $channel?->value ?? self::ALL)
            ->where('event_key', $eventKey ?? self::ALL)
            ->delete();
    }
}
