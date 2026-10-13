<?php

declare(strict_types=1);

namespace Shardevcom\Socialite\Contracts;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Abstract base class for Socialite drivers.
 *
 * Provides common functionality for OAuth2 providers.
 * Can be extended by custom drivers.
 */
abstract class AbstractSocialiteDriver implements SocialiteDriver
{
    /**
     * @var string The configuration key (e.g., 'google', 'microsoft')
     */
    protected string $configKey;

    public function __construct()
    {
        $this->configKey = $this->key();
    }

    public function getConfigKey(): string
    {
        return $this->configKey;
    }

    public function isEnabled(): bool
    {
        $config = Config::get("socialite.providers.{$this->configKey}");
        return $config['enabled'] ?? false;
    }

    public function getScopes(): array
    {
        $config = Config::get("socialite.providers.{$this->configKey}");
        return $config['scopes'] ?? [];
    }

    public function getConfigRedirectUrl(): string
    {
        $config = Config::get("socialite.providers.{$this->configKey}");
        return $config['redirect'] ?? Config::get("socialite.default_redirect", '/dashboard');
    }

    public function getRedirectUrl(): string
    {
        try {
            $driver = Socialite::driver($this->configKey);

            if ($scopes = $this->getScopes()) {
                $driver->scopes($this->getScopes());
            }

            return $driver->stateless()->redirect()->getTargetUrl();
        } catch (\Throwable $e) {
            Log::error("Socialite redirect failed for {$this->configKey}: " . $e->getMessage());
            throw $e;
        }
    }

    public function handleCallback(): \Laravel\Socialite\Contracts\User
    {
        try {
            return Socialite::driver($this->configKey)->stateless()->user();
        } catch (\Throwable $e) {
            Log::error("Socialite callback failed for {$this->configKey}: " . $e->getMessage());
            throw $e;
        }
    }

    public function findOrCreateUser(\Laravel\Socialite\Contracts\User $socialiteUser): \App\Models\User
    {
        $user = \App\Models\User::where('email', $socialiteUser->getEmail())->first();

        if ($user) {
            $user->update([
                "{$this->configKey}_id" => $socialiteUser->getId(),
                "avatar" => $socialiteUser->getAvatar(),
            ]);

            return $user;
        }

        return \App\Models\User::create([
            'name' => $socialiteUser->getName() ?? $socialiteUser->getEmail(),
            'email' => $socialiteUser->getEmail(),
            'email_verified_at' => now(),
            "{$this->configKey}_id" => $socialiteUser->getId(),
            'avatar' => $socialiteUser->getAvatar(),
            'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)),
        ]);
    }
}