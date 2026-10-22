<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Application\Queries;

use Shardevcom\Communications\Contracts\PreferenceStore;

final class GetPreferences
{
    public function __construct(private readonly PreferenceStore $preferences)
    {
    }

    /**
     * @return list<array{channel: string, event_key: string, reason: ?string, opted_out_at: ?string}>
     */
    public function execute(string $guard, string $userId): array
    {
        return $this->preferences->listFor($guard, $userId);
    }
}
