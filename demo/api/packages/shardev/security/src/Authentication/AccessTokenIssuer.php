<?php

declare(strict_types=1);

namespace Shardevcom\Security\Authentication;

use Shardevcom\Core\Contracts\ResourceRegistry;

/**
 * Mints the first-party personal access token a successful authentication
 * returns.
 *
 * Shared by the login and registration adapters so the two cannot drift: a
 * token minted by one must carry exactly the abilities of the other, or a user
 * who registered would hold a token that the API treats differently from the
 * one they get by logging in.
 */
final class AccessTokenIssuer
{
    public function __construct(
        private readonly ScopeCatalogue $scopes,
        private readonly ResourceRegistry $registry,
    ) {}

    /**
     * @return array{token_type: string, access_token: string, expires_in: int|null, abilities: list<string>}
     */
    public function issue(object $user, string $device): array
    {
        $abilities = $this->scopes->abilities($this->registry);
        $issued = $user->createToken($device, $abilities);

        return [
            'token_type' => $issued->tokenType ?? 'Bearer',
            'access_token' => $issued->accessToken,
            'expires_in' => $issued->expiresIn,
            'abilities' => $abilities,
        ];
    }

    /**
     * @return list<string>
     */
    public function abilities(): array
    {
        return $this->scopes->abilities($this->registry);
    }
}
