<?php

declare(strict_types=1);

namespace Shardevcom\Core\Authentication;

/**
 * What storage knows about a token, expressed without framework types.
 *
 * This is deliberately NOT the ApplicationIdentity: a record is what the
 * store can prove about the token, while the identity is what the platform
 * concludes about the caller. Keeping them apart means a resolver cannot
 * smuggle in its own idea of who the user is.
 *
 * The raw token is intentionally absent. Once storage has matched a token the
 * secret has served its purpose, and keeping it around in a value object
 * only widens the blast radius of a log or var_dump accident.
 */
final class TokenRecord
{
    /**
     * @param string               $id         store-side identifier, safe to log (never the secret)
     * @param string               $actorId    the authenticated principal
     * @param list<string>         $abilities  token abilities ('*' means unrestricted)
     * @param int|null             $expiresAt  unix timestamp, null = never expires
     * @param int|null             $revokedAt  unix timestamp, null = not revoked
     * @param array<string, mixed> $device     device/session context for observability, e.g. ['name' => 'iPhone']
     */
    public function __construct(
        public readonly string $id,
        public readonly string $actorId,
        public readonly string $kind = 'user',
        public readonly array $abilities = [],
        public readonly ?int $expiresAt = null,
        public readonly ?int $revokedAt = null,
        public readonly array $device = [],
    ) {
    }

    public function isExpired(int $now): bool
    {
        return $this->expiresAt !== null && $this->expiresAt <= $now;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function isUsable(int $now): bool
    {
        return ! $this->isExpired($now) && ! $this->isRevoked();
    }

    public function can(string $ability): bool
    {
        return in_array('*', $this->abilities, true) || in_array($ability, $this->abilities, true);
    }
}