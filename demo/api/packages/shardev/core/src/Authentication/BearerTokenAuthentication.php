<?php

declare(strict_types=1);

namespace Shardevcom\Core\Authentication;

use Shardevcom\Core\ApplicationIdentity;
use Shardevcom\Core\Contracts\AuthenticationProvider;
use Shardevcom\Core\Contracts\TokenResolver;
use Shardevcom\Core\Exceptions\Unauthorized;

/**
 * Bearer token strategy for stateless APIs (T-090).
 *
 * Stateless means every request carries its own proof: no cookie, no session,
 * therefore nothing for a browser to attach automatically and nothing for
 * CSRF to protect. See docs/authentication.md for why that removes the threat
 * instead of merely ignoring it.
 *
 * The clock is injected because expiry is a security decision; a test that
 * cannot move time cannot test it.
 */
final class BearerTokenAuthentication implements AuthenticationProvider
{
    /**
     * @var \Closure(): int
     */
    private \Closure $now;

    /**
     * @param (\Closure(): int)|null $now returns a unix timestamp
     */
    public function __construct(
        private readonly TokenResolver $tokens,
        ?callable $now = null,
    ) {
        $this->now = $now === null
            ? static fn (): int => time()
            : \Closure::fromCallable($now);
    }

    public function identityFromToken(string $token): ApplicationIdentity
    {
        $token = trim($token);

        if ($token === '') {
            throw new Unauthorized('A bearer token is required.', ['reason' => 'missing']);
        }

        $record = $this->tokens->resolve($token);

        // Unknown and revoked/expired all end in the same exception on purpose:
        // telling a caller which of the three happened turns the endpoint into
        // an oracle for valid token ids. The reason is kept for logs, not for
        // the response body.
        if ($record === null) {
            throw new Unauthorized('The provided credentials are not valid.', ['reason' => 'unknown']);
        }

        $now = ($this->now)();

        if ($record->isRevoked()) {
            throw new Unauthorized('The provided credentials are not valid.', [
                'reason' => 'revoked',
                'token_id' => $record->id,
            ]);
        }

        if ($record->isExpired($now)) {
            throw new Unauthorized('The provided credentials are not valid.', [
                'reason' => 'expired',
                'token_id' => $record->id,
                'expired_at' => $record->expiresAt,
            ]);
        }

        return new ApplicationIdentity(
            id: $record->actorId,
            kind: $record->kind,
            attributes: [
                'token_id' => $record->id,
                'abilities' => $record->abilities,
                'device' => $record->device,
                'expires_at' => $record->expiresAt,
                'via' => 'bearer',
            ],
        );
    }

    /**
     * Cookie/session and Bearer are different strategies behind the same
     * contract, never one strategy with two branches: session auth is stateful
     * (and must be CSRF-protected by the web layer), Bearer is not.
     */
    public function accepts(string $headerValue): bool
    {
        return str_starts_with(strtolower(trim($headerValue)), 'bearer ');
    }
}