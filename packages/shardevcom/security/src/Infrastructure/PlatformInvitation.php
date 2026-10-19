<?php

declare(strict_types=1);

namespace Shardevcom\Security\Infrastructure;

use Illuminate\Database\Eloquent\Model;

/**
 * An invitation for an email to join a platform (guard).
 *
 * The plain token is only ever in the emailed link; the row stores its SHA-256
 * hash so a database leak does not hand out usable invitations.
 *
 * @property string $guard
 * @property string $email
 * @property string $token
 * @property int|string|null $invited_by
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $accepted_at
 */
final class PlatformInvitation extends Model
{
    protected $table = 'platform_invitations';

    /** @var list<string> */
    protected $fillable = ['guard', 'email', 'token', 'invited_by', 'expires_at', 'accepted_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    public function isPending(): bool
    {
        if ($this->accepted_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
