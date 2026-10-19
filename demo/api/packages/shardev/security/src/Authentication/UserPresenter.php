<?php

declare(strict_types=1);

namespace Shardevcom\Security\Authentication;

/**
 * The user shape every auth adapter returns.
 *
 * A single projection shared by login, registration and `auth/me`: the SPA
 * keys its session off these fields, so they have to be identical regardless
 * of which endpoint produced the identity.
 */
final class UserPresenter
{
    /**
     * @param list<string> $abilities
     *
     * @return array{id: string, name: mixed, email: mixed, guard: mixed, email_verified: bool, abilities: list<string>}
     */
    public function present(object $user, array $abilities): array
    {
        return [
            'id' => (string) $user->getAuthIdentifier(),
            'name' => $user->name ?? null,
            'email' => $user->email ?? null,
            'guard' => method_exists($user, 'getGuard') ? $user->getGuard() : null,
            'email_verified' => method_exists($user, 'hasVerifiedEmail') ? (bool) $user->hasVerifiedEmail() : true,
            'abilities' => $abilities,
        ];
    }
}
