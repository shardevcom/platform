<?php

declare(strict_types=1);

namespace Shardevcom\Security\Infrastructure;

use Illuminate\Auth\AuthManager;

/**
 * Creates and looks up platform accounts through the platform's own provider
 * and model.
 *
 * Shared by self-registration and invitation acceptance so both create an
 * account the exact same way (the model's `password` cast hashes the plain
 * value) and cannot drift.
 */
final class UserRegistrar
{
    public function __construct(private readonly AuthManager $auth) {}

    /**
     * @param array<string, mixed> $platform
     */
    public function exists(array $platform, string $email): bool
    {
        return $this->find($platform, $email) !== null;
    }

    /**
     * @param array<string, mixed> $platform
     */
    public function find(array $platform, string $email): ?object
    {
        $provider = $this->auth->createUserProvider($platform['provider'] ?? null);

        return $provider?->retrieveByCredentials(['email' => $email]);
    }

    /**
     * @param array<string, mixed> $platform
     */
    public function findById(array $platform, int|string $id): ?object
    {
        $provider = $this->auth->createUserProvider($platform['provider'] ?? null);

        return $provider?->retrieveById($id);
    }

    /**
     * @param array<string, mixed> $platform
     */
    public function create(array $platform, string $name, string $email, string $password, bool $verified = false): ?object
    {
        $model = (string) ($platform['model'] ?? '');

        if (! class_exists($model)) {
            return null;
        }

        $user = new $model();
        $user->forceFill([
            'name' => trim($name),
            'email' => $email,
            'password' => $password,
            'email_verified_at' => $verified ? now() : null,
        ])->save();

        return $user;
    }
}
