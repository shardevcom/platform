<?php

declare(strict_types=1);

namespace Shardevcom\Security\Infrastructure;

use Illuminate\Database\Eloquent\Model;

/**
 * A per-user, per-guard account lock. A non-null `disabled_at` means the
 * account is disabled: it cannot log in and its active tokens are rejected.
 *
 * @property string $guard
 * @property int|string $user_id
 * @property \Illuminate\Support\Carbon|null $disabled_at
 * @property string|null $reason
 */
final class UserLock extends Model
{
    protected $table = 'platform_user_locks';

    /** @var list<string> */
    protected $fillable = ['guard', 'user_id', 'disabled_at', 'reason'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['disabled_at' => 'datetime'];
    }
}
