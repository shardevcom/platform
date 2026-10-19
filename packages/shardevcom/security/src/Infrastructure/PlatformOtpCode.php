<?php

declare(strict_types=1);

namespace Shardevcom\Security\Infrastructure;

use Illuminate\Database\Eloquent\Model;

/**
 * A one-time code for passwordless login.
 *
 * The plain code is only ever in the email; the row stores its SHA-256 hash.
 * `attempts` bounds how many wrong guesses a code tolerates before it is
 * consumed.
 *
 * @property string $guard
 * @property string $email
 * @property string $code
 * @property int $attempts
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $consumed_at
 */
final class PlatformOtpCode extends Model
{
    protected $table = 'platform_otp_codes';

    /** @var list<string> */
    protected $fillable = ['guard', 'email', 'code', 'attempts', 'expires_at', 'consumed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'consumed_at' => 'datetime'];
    }

    public function isPending(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture();
    }
}
