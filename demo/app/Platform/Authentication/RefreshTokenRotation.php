<?php

declare(strict_types=1);

namespace App\Platform\Authentication;

use Laravel\Passport\Token;
use Laravel\Passport\Passport;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Refresh Token Rotation Service (T-CASA-007).
 *
 * When a refresh token is used to obtain a new access token:
 * 1. The used refresh token is revoked
 * 2. A new refresh token is issued
 * 3. The new refresh token has its own expiry and family ID
 *
 * This prevents replay attacks: if a refresh token is compromised,
 * the attacker can only use it once before it's rotated.
 */
final class RefreshTokenRotation
{
    public function __construct(
        private readonly Carbon $clock = null
    ) {
        $this->clock ??= Carbon::now();
    }

    /**
     * Rotate a refresh token: revoke the old one, create a new one.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, token_type: string, scope: string}
     */
    public function rotate(string $refreshToken): array
    {
        return DB::transaction(function () use ($refreshToken) {
            // Find the refresh token
            $token = Token::where('id', $refreshToken)->first();

            if ($token === null) {
                throw new \LogicException('Refresh token not found');
            }

            // Check if it's a refresh token
            if (! $token->revoked && $token->expires_at && $token->expires_at->isFuture()) {
                // Revoke the old refresh token
                $token->revoked = true;
                $token->save();

                // Create new access token with same scopes
                $accessToken = $token->accessToken;

                // Create new refresh token with same family
                $newRefreshToken = $this->createRefreshToken($accessToken);

                return [
                    'access_token' => $accessToken,
                    'refresh_token' => $newRefreshToken->id,
                    'expires_in' => config('passport.access_token_expires_in', 3600),
                    'token_type' => 'Bearer',
                    'scope' => implode(' ', $accessToken->scopes->pluck('scope')->toArray()),
                ];
            }

            throw new \LogicException('Refresh token expired or revoked');
        });
    }

    /**
     * Create a new refresh token for an access token.
     */
    private function createRefreshToken(Token $accessToken): Token
    {
        $expiresAt = $this->clock->copy()->add(
            Carbon::parse(config('passport.refresh_token_expires_in'))->diffForHumans()
        );

        // Actually use Passport's internal method
        return $accessToken->refreshToken()->create([
            'user_id' => $accessToken->user_id,
            'client_id' => $accessToken->client_id,
            'name' => $accessToken->name,
            'scopes' => $accessToken->scopes,
            'revoked' => false,
            'expires_at' => $expiresAt,
        ]);
    }
}