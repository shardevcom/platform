<?php

declare(strict_types=1);

namespace Shardevcom\Socialite\Providers;

use Shardevcom\Socialite\Contracts\AbstractSocialiteDriver;
use Shardevcom\Socialite\Contracts\SocialiteDriver;

/**
 * Microsoft OAuth2 driver.
 *
 * Can be overridden by user configuration in config/socialite.php
 */
final class MicrosoftDriver extends AbstractSocialiteDriver implements SocialiteDriver
{
    public function key(): string
    {
        return 'microsoft';
    }

    public function name(): string
    {
        return 'Microsoft';
    }

    public function getScopes(): array
    {
        $config = config('socialite.providers.microsoft', []);
        return $config['scopes'] ?? ['openid', 'profile', 'email'];
    }
}