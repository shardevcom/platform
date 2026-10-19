<?php

declare(strict_types=1);

namespace Shardevcom\Security\Infrastructure;

/**
 * Reads and writes the per-platform account lock.
 *
 * It is the single place that answers "is this account disabled?" so the login
 * adapter, the account adapter and the request middleware cannot drift apart.
 */
final class AccountLock
{
    public function isDisabled(string $guard, int|string $userId): bool
    {
        return UserLock::query()
            ->where('guard', $guard)
            ->where('user_id', $userId)
            ->whereNotNull('disabled_at')
            ->exists();
    }

    public function disable(string $guard, int|string $userId, ?string $reason = null): void
    {
        $lock = UserLock::query()->firstOrNew([
            'guard' => $guard,
            'user_id' => $userId,
        ]);

        $lock->disabled_at = now();
        $lock->reason = $reason;
        $lock->save();
    }

    public function enable(string $guard, int|string $userId): void
    {
        UserLock::query()
            ->where('guard', $guard)
            ->where('user_id', $userId)
            ->delete();
    }
}