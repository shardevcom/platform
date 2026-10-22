<?php

declare(strict_types=1);

namespace Shardevcom\Core\Contracts;

/**
 * Boundary: asks the installed second-factor provider whether a successful
 * credential check still needs another step before a token is minted.
 *
 * The login adapter owns credential validation; it must not know TOTP,
 * recovery codes or whether a guard requires MFA. Implementations decide
 * that and return the transport-neutral challenge payload, or null when the
 * session may be issued immediately.
 */
interface SecondFactorGate
{
    /**
     * @return array<string, mixed>|null null when no second factor is required
     */
    public function challenge(object $user, string $guard): ?array;
}
