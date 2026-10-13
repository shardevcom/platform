<?php

declare(strict_types=1);

namespace App\Platform\Authentication;

use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use Psr\Http\Message\ServerRequestInterface;
use Shardevcom\Core\Authentication\TokenRecord;
use Shardevcom\Core\Contracts\TokenResolver;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * Passport-backed token store (T-090).
 *
 * Same two omissions as the previous store, for the same reasons: this adapter
 * does not load the user (hydration is the context builder's job) and it does
 * not decide what a record *means* for the caller.
 *
 * Where it differs is the credential itself. Sanctum rows are opaque
 * id+hash pairs, so finding the row was all the store could do. A Passport
 * access token is a self-contained signed JWT: without checking the signature
 * the only thing tying a request to a row would be the `jti`, which is the
 * same value TokenRecord::id documents as "safe to log". Verifying the
 * signature is therefore not optional — it is what keeps the record id from
 * being a bearer credential on its own.
 *
 * Signature verification, expiry and revocation are one call into League's
 * ResourceServer, the library that minted the token; a failure there means the
 * store cannot vouch for the token and surfaces as "unknown". Expiry written
 * on the row is still honoured by BearerTokenAuthentication, because the JWT
 * claim is not moved when a row is edited.
 */
final class PassportTokenResolver implements TokenResolver
{
    public function __construct(private readonly ResourceServer $server)
    {
    }

    public function resolve(string $token): ?TokenRecord
    {
        try {
            $validated = $this->server->validateAuthenticatedRequest($this->psrRequest($token));
        } catch (OAuthServerException) {
            return null;
        }

        $id = (string) $validated->getAttribute('oauth_access_token_id');
        $row = Passport::token()->newQuery()->with('client')->find($id);

        if ($row === null) {
            return null;
        }

        $actorId = $validated->getAttribute('oauth_user_id');

        if (! is_string($actorId) || $actorId === '') {
            return null;
        }

        return new TokenRecord(
            id: $id,
            actorId: $actorId,
            kind: $this->kindOf($row),
            abilities: array_values((array) $validated->getAttribute('oauth_scopes', [])),
            expiresAt: $row->expires_at?->getTimestamp(),
            revokedAt: $row->revoked ? $row->updated_at?->getTimestamp() : null,
            device: array_filter(
                ['name' => $row->name],
                static fn (mixed $value): bool => $value !== null
            ),
        );
    }

    /**
     * League validates against a PSR-7 server request, so the raw credential is
     * wrapped in a synthetic one instead of mutating the live request.
     */
    private function psrRequest(string $token): ServerRequestInterface
    {
        return (new PsrHttpFactory())->createRequest(
            SymfonyRequest::create(
                '/',
                'GET',
                [],
                [],
                [],
                ['HTTP_AUTHORIZATION' => 'Bearer '.$token]
            )
        );
    }

    /**
     * The token row carries no morph type, and the resolver deliberately does
     * not load the user to find one. The issuing client's provider names the
     * platform, and the platform names the model — which is the same basename
     * a morph column would have carried.
     */
    private function kindOf(Token $row): string
    {
        $provider = $row->client?->provider;
        $model = is_string($provider) ? config("platforms.platforms.{$provider}.model") : null;

        return is_string($model) && $model !== '' ? class_basename($model) : 'user';
    }
}
