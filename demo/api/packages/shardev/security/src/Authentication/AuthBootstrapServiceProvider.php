<?php

declare(strict_types=1);

namespace Shardevcom\Security\Authentication;

use Illuminate\Auth\AuthManager;
use Illuminate\Auth\TokenGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\ServiceProvider;

/**
 * Auth bootstrap dinámico (T-201).
 *
 * Registra guards, providers y password resets dinámicamente
 * basándose en config('platforms.platforms').
 *
 * Lives in the security adapter: it wires Eloquent user providers, which is an
 * infrastructure concern and must stay out of core.
 */
final class AuthBootstrapServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureAuth();
    }

    private function configureAuth(): void
    {
        $platforms = Config::get('platforms.platforms', []);

        if (empty($platforms)) {
            return;
        }

        // Build guards and providers config arrays
        $guards = Config::get('auth.guards', []);
        $providers = Config::get('auth.providers', []);

        foreach ($platforms as $platform) {
            $this->registerGuard($platform, $guards);
            $this->registerProvider($platform, $providers);
            $this->registerPasswordReset($platform);
        }

        // Write back to config
        Config::set('auth.guards', $guards);
        Config::set('auth.providers', $providers);

        // Override default guard if specified
        $defaultPlatform = Config::get('platforms.default', 'web');
        Config::set('auth.defaults.guard', $defaultPlatform);
    }

    private function registerGuard(array $platform, array &$guards): void
    {
        $guard = $platform['guard'];
        $driver = $platform['guard_driver'] ?? 'session';
        $provider = $platform['provider'];

        // Add to config so AuthManager::resolve() can find it
        $guards[$guard] = [
            'driver' => $driver,
            'provider' => $provider,
        ];

        // A session-guarded platform still needs a token guard over the same
        // provider. Passport answers "which provider may this user mint tokens
        // for?" by scanning config('auth.guards') for driver 'passport'
        // (HasApiTokens::getProviderName) and throws when none qualifies, so
        // without this sibling guard createToken() is unreachable for the
        // platform no matter how valid the credentials are. The guard is never
        // routed to: route groups resolve their own guard from the platform key.
        $apiGuard = $platform['api_guard'] ?? null;

        if (is_string($apiGuard) && $apiGuard !== '') {
            $guards[$apiGuard] = [
                'driver' => 'passport',
                'provider' => $provider,
            ];
        }

        // Register custom creator for token driver (Laravel's built-in token guard)
        if ($driver === 'token') {
            Auth::extend($guard, function ($app, $name, array $config) use ($driver, $provider) {
                $guardConfig = array_merge($config, [
                    'driver' => $driver,
                    'provider' => $provider,
                ]);

                return new TokenGuard(
                    $app->make(\Illuminate\Contracts\Auth\Factory::class),
                    $app->make(\Illuminate\Http\Request::class),
                    $app->make(\Illuminate\Contracts\Auth\UserProvider::class, $provider),
                );
            });
        }

        // Driver 'passport' needs no creator here: PassportServiceProvider
        // registers it, and AppServiceProvider replaces it with the stateless
        // variant. Only the config entry above has to exist.
    }

    private function registerProvider(array $platform, array &$providers): void
    {
        $provider = $platform['provider'];
        $model = $platform['model'];
        $table = $platform['table'];

        // Add to config
        $providers[$provider] = [
            'driver' => 'eloquent',
            'model' => $model,
        ];

        // Also register custom provider resolver
        Auth::provider($provider, function ($app) use ($model, $table) {
            return new \Illuminate\Auth\EloquentUserProvider(
                $app->make(\Illuminate\Contracts\Hashing\Hasher::class),
                $model,
            );
        });

        // Ensure model uses correct table
        if (class_exists($model)) {
            $modelInstance = new $model();
            if (!$modelInstance->getTable()) {
                $modelInstance->setTable($table);
            }
        }
    }

    private function registerPasswordReset(array $platform): void
    {
        $reset = $platform['password_reset'];
        $table = $platform['password_reset_table'] ?? $platform['table'] . '_password_resets';

        Config::set("auth.passwords.{$reset}", [
            'provider' => $platform['provider'],
            'table' => $table,
            'expire' => 60,
            'throttle' => 60,
        ]);
    }
}
