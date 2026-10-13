<?php

declare(strict_types=1);

namespace Shardevcom\Core\Contracts;

use Shardevcom\Core\ApplicationIdentity;

/**
 * Boundary: proof of "who is calling" independent of transport.
 * Implementations (Passport bearer, session cookie, CLI, test fake) produce
 * the same ApplicationIdentity, so authorization and context building never
 * depend on HTTP details. Token mechanics (expiry, revocation, device
 * scope) live behind this contract, not in adapters.
 */
interface AuthenticationProvider
{
    public function identityFromToken(string $token): ApplicationIdentity;
}
