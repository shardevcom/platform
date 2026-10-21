<?php

declare(strict_types=1);

namespace Shardevcom\Socialite\Contracts;

use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Contract for a socialite driver.
 *
 * Allows custom OAuth2 providers to be implemented and registered.
 * The default Google and Microsoft drivers can be overridden by user configuration.
 */
interface SocialiteDriver
{
    /**
     * Get the unique key for this driver (e.g., 'google', 'microsoft').
     */
    public function key(): string;

    /**
     * Get the display name for this driver.
     */
    public function name(): string;

    /**
     * Check if this driver is enabled.
     */
    public function isEnabled(): bool;

    /**
     * URL the browser must visit to start the provider's OAuth flow.
     *
     * @param  string|null  $state  Opaque value echoed back by the provider
     *                              (used by native clients to receive the
     *                              token on their own callback).
     */
    public function getRedirectUrl(?string $state = null): string;

    /**
     * Handle the OAuth callback and return the socialite user.
     */
    public function handleCallback(): \Laravel\Socialite\Contracts\User;

    /**
     * Find or create a user from the socialite user data.
     */
    public function findOrCreateUser(
        \Laravel\Socialite\Contracts\User $socialiteUser
    ): \Illuminate\Contracts\Auth\Authenticatable;

    /**
     * Get the configuration key for this driver (e.g., 'google', 'microsoft').
     */
    public function getConfigKey(): string;

    /**
     * Get the scopes for this provider.
     *
     * @return list<string>
     */
    public function getScopes(): array;
}