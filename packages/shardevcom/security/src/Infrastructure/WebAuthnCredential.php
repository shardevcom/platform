<?php

declare(strict_types=1);

namespace Shardevcom\Security\Infrastructure;

use Illuminate\Database\Eloquent\Model;

/**
 * A registered WebAuthn credential (passkey) for a platform user.
 *
 * `record` holds the library's `CredentialRecord` as JSON; `credential_id` is
 * the base64url (unpadded) raw credential id used for lookups.
 *
 * @property string $guard
 * @property int|string $user_id
 * @property string $credential_id
 * @property string $record
 * @property int $counter
 * @property string|null $name
 * @property \Illuminate\Support\Carbon|null $last_used_at
 */
final class WebAuthnCredential extends Model
{
    protected $table = 'platform_webauthn_credentials';

    /** @var list<string> */
    protected $fillable = ['guard', 'user_id', 'credential_id', 'record', 'counter', 'name', 'last_used_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }
}
