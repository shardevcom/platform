<?php

declare(strict_types=1);

namespace App\Providers;

use App\Platform\Authentication\PassportTokenResolver;
use App\Platform\Authentication\StatelessPassportGuard;
use App\Platform\Authentication\TokenScopeCatalogue;
use App\Platform\Authentication\RefreshTokenRotation;
use App\Platform\Authentication\StepUpMfa;
use App\Platform\Authorization\ContextPermissionResolver;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\ServiceProvider;
use League\OAuth2\Server\ResourceServer;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportUserProvider;
use Shardevcom\Core\Authentication\BearerTokenAuthentication;
use Shardevcom\Core\Contracts\AuthenticationProvider;
use Shardevcom\Core\Contracts\PermissionResolver;
use Shardevcom\Core\Contracts\ResourceRegistry;
use Shardevcom\Core\Contracts\TokenResolver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Storage is chosen here; the rules about expiry, revocation and
        // abilities are the platform's, in BearerTokenAuthentication. Swapping
        // Passport for another store is a one-line change in this provider.
        $this->app->singleton(TokenResolver::class, PassportTokenResolver::class);
        $this->app->singleton(AuthenticationProvider::class, BearerTokenAuthentication::class);
        $this->app->singleton(PermissionResolver::class, ContextPermissionResolver::class);
        $this->app->singleton(RefreshTokenRotation::class);
        $this->app->singleton(StepUpMfa::class);

        // Configure Passport token lifetimes
        $this->configurePassportLifetimes();
    }

    /**
     * Configure Passport token lifetimes from config.
     */
    private function configurePassportLifetimes(): void
    {
        $accessTokenTtl = config('passport.access_token_expires_in', '1 hour');
        $refreshTokenTtl = config('passport.refresh_token_expires_in', '7 days');
        $personalAccessTokenTtl = config('passport.personal_access_token_expires_in', '1 year');

        Passport::tokensExpireIn($this->parseDuration($accessTokenTtl));
        Passport::refreshTokensExpireIn($this->parseDuration($refreshTokenTtl));
        Passport::personalAccessTokensExpireIn($this->parseDuration($personalAccessTokenTtl));

        // Refresh token rotation is handled by Passport's default behavior
        // when using the refresh_token grant. T-CASA-007.
    }

    private function parseDuration(string $duration): \DateInterval
    {
        // Parse strings like "1 hour", "7 days", "1 year" into DateInterval
        $parts = preg_split('/\s+/', trim($duration), 2);
        if (count($parts) !== 2) {
            return new \DateInterval('PT1H'); // default 1 hour
        }

        [$value, $unit] = $parts;
        $value = (int) $value;

        return match (strtolower($unit)) {
            'second', 'seconds' => new \DateInterval('PT'.$value.'S'),
            'minute', 'minutes' => new \DateInterval('PT'.$value.'M'),
            'hour', 'hours' => new \DateInterval('PT'.$value.'H'),
            'day', 'days' => new \DateInterval('P'.$value.'D'),
            'week', 'weeks' => new \DateInterval('P'.$value.'W'),
            'month', 'months' => new \DateInterval('P'.$value.'M'),
            'year', 'years' => new \DateInterval('P'.$value.'Y'),
            default => new \DateInterval('PT1H'),
        };
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->replacePassportGuard();
        $this->declareTokenScopes();
    }

    /**
     * Swap Passport's guard for the stateless one.
     *
     * AuthManager reuses one guard instance per name for the life of the
     * process, so the principal Passport resolves on the first request would
     * keep answering for later ones. Statelessness is not a Passport-specific
     * concern, but the constructor of its guard is, which is why the subclass
     * lives next to the resolver instead of in Core.
     */
    private function replacePassportGuard(): void
    {
        /** @var AuthFactory $auth */
        $auth = $this->app->make(AuthFactory::class);

        $auth->extend('passport', function ($app, $name, array $config) {
            $guard = new StatelessPassportGuard(
                $app->make(ResourceServer::class),
                new PassportUserProvider(
                    $app['auth']->createUserProvider($config['provider']),
                    $config['provider']
                ),
                $app->make(ClientRepository::class),
                $app->make('encrypter'),
                $app->make('request'),
            );

            $app->refresh('request', $guard, 'setRequest');

            return $guard;
        });
    }

    /**
     * Declare the permissions a personal access token may be limited to.
     *
     * Passport refuses to mint a token carrying a scope it has never heard of,
     * so anything missing here does not fail loudly at the call site: it fails
     * as an exception inside createToken(). The catalogue is derived from the
     * resource registry rather than written out by hand for the same reason the
     * permission allow-list is: a new action on a resource must not need a
     * second edit to become issuable.
     *
     * Deferring to booted() because resources publish their definitions on
     * boot and this provider cannot know it runs after them.
     */
    private function declareTokenScopes(): void
    {
        $this->app->booted(function (): void {
            Passport::tokensCan(
                (new TokenScopeCatalogue())->scopes($this->app->make(ResourceRegistry::class))
            );
        });
    }
}
