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
     * Get the OAuth redirect URL for this provider (the actual URL to redirect to).
     */
    public function getRedirectUrl(): string;

    /**
     * Handle the OAuth callback and return the socialite user.
     */
    public function handleCallback(): \Laravel\Socialite\Contracts\User;

    /**
     * Find or create a user from the socialite user data.
     */
    public function findOrCreateUser(\Laravel\Socialite\Contracts\User $socialiteUser): \App\Models\User;

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