<?php

declare(strict_types=1);

namespace Shardevcom\Socialite\Providers;

use Shardevcom\Socialite\Contracts\AbstractSocialiteDriver;
use Shardevcom\Socialite\Contracts\SocialiteDriver;

/**
 * Google OAuth2 driver.
 *
 * Can be overridden by user configuration in config/socialite.php
 */
final class GoogleDriver extends AbstractSocialiteDriver implements SocialiteDriver
{
    public function key(): string
    {
        return 'google';
    }

    public function name(): string
    {
        return 'Google';
    }

    public function getScopes(): array
    {
        $config = config('socialite.providers.google', []);
        return $config['scopes'] ?? ['openid', 'profile', 'email'];
    }
}