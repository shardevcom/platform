<?php

declare(strict_types=1);

namespace Shardevcom\Core\Contracts;

use Shardevcom\Core\Authentication\TokenRecord;

/**
 * Boundary: the ONLY place that knows where tokens live.
 *
 * Core decides *whether* a token proves an identity (expiry, revocation) and
 * never *where* it is stored. Passport, a JWT verifier, a database table or an
 * in-memory fake all answer the same question. Keeping storage behind this
 * seam is what lets the Bearer rules be unit-tested without a framework and
 * lets a project swap its token technology without touching authorization.
 *
 * Implementations must return null when the token is unknown — never throw.
 * An unknown token is an expected outcome, not an error: the caller cannot be
 * distinguished from an attacker guessing ids.
 */
interface TokenResolver
{
    public function resolve(string $token): ?TokenRecord;
}