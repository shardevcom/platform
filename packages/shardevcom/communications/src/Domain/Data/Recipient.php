<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Domain\Data;

use Shardevcom\Communications\Domain\Enums\Channel;

/**
 * Transport-neutral recipient.
 *
 * `addresses` maps channel values to destinations, e.g.
 * ['email' => 'ana@example.com', 'phone' => '+34600000000'].
 * Preferences are keyed by (guard, id), so a user recipient must always carry
 * both to be checked against opt-outs.
 */
final class Recipient
{
    public const TYPE_USER = 'user';

    public const TYPE_ADMIN = 'admin';

    /**
     * @param array<string, string> $addresses
     * @param list<string> $roles
     */
    public function __construct(
        public readonly string $type,
        public readonly ?string $id = null,
        public readonly ?string $guard = null,
        public readonly array $addresses = [],
        public readonly array $roles = [],
        public readonly ?string $locale = null,
    ) {
    }

    /**
     * @param array<string, string> $addresses
     */
    public static function user(
        string $id,
        string $guard,
        array $addresses = [],
        ?string $locale = null,
    ): self {
        return new self(self::TYPE_USER, $id, $guard, $addresses, [], $locale);
    }

    /**
     * @param array<string, string> $addresses
     * @param list<string> $roles
     */
    public static function admin(
        ?string $id,
        string $guard = 'admin',
        array $addresses = [],
        array $roles = [],
        ?string $locale = null,
    ): self {
        return new self(self::TYPE_ADMIN, $id, $guard, $addresses, $roles, $locale);
    }

    public function addressFor(Channel $channel): ?string
    {
        $address = match ($channel) {
            Channel::EMAIL => $this->addresses['email'] ?? null,
            Channel::SMS => $this->addresses['sms'] ?? $this->addresses['phone'] ?? null,
            Channel::WHATSAPP => $this->addresses['whatsapp'] ?? $this->addresses['phone'] ?? null,
            Channel::PUSH => $this->addresses['push'] ?? $this->addresses['push_token'] ?? null,
            Channel::IN_APP => null,
        };

        return $address === null || trim($address) === '' ? null : $address;
    }

    /**
     * Internal admin alerts are operational: they are not silenced by a
     * customer's marketing preferences.
     */
    public function honorsPreferences(): bool
    {
        return $this->type === self::TYPE_USER;
    }

    public function isAdmin(): bool
    {
        return $this->type === self::TYPE_ADMIN;
    }
}
