<?php

declare(strict_types=1);

namespace Shardevcom\Socialite;

use Shardevcom\Core\Exceptions\ResourceNotFound;
use Shardevcom\Socialite\Contracts\SocialiteDriver;

/**
 * Registry of socialite drivers.
 *
 * Allows registering and resolving OAuth2 drivers (Google, Microsoft, custom).
 * Drivers can be enabled/disabled via configuration.
 */
final class SocialiteRegistry
{
    /**
     * @var array<string, SocialiteDriver>
     */
    private array $drivers = [];

    public function register(SocialiteDriver $driver): void
    {
        $key = $driver->key();

        if (isset($this->drivers[$key])) {
            throw new \InvalidArgumentException("Socialite driver '{$key}' is already registered.");
        }

        $this->drivers[$key] = $driver;
    }

    public function has(string $key): bool
    {
        return isset($this->drivers[$key]);
    }

    public function get(string $key): SocialiteDriver
    {
        if (!isset($this->drivers[$key])) {
            throw new ResourceNotFound('Socialite driver [' . $key . '] not found.');
        }

        return $this->drivers[$key];
    }

    /**
     * @return array<string, SocialiteDriver>
     */
    public function all(): array
    {
        return $this->drivers;
    }

    /**
     * Get all enabled drivers.
     *
     * @return array<string, SocialiteDriver>
     */
    public function enabled(): array
    {
        return array_filter($this->drivers, fn ($driver) => $driver->isEnabled());
    }

    public function getEnabledKeys(): array
    {
        return array_keys($this->enabled());
    }
}