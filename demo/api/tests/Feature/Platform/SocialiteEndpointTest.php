<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Shardevcom\Socialite\Contracts\SocialiteDriver;
use Shardevcom\Socialite\SocialiteRegistry;
use Tests\TestCase;

/**
 * The OAuth surface: the provider catalog, the redirect hand-off and the
 * callback. The driver is faked so the contract is exercised without hitting
 * a real provider; the callback must answer JSON to API clients and send the
 * SPA back to its own route with the token in the fragment for browsers.
 */
final class SocialiteEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ]);

        app(SocialiteRegistry::class)->register($this->driver());
    }

    public function test_it_lists_the_enabled_providers(): void
    {
        $this->getJson('/api/v1/auth/social/providers')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonFragment(['key' => 'fake', 'name' => 'Fake']);
    }

    public function test_it_returns_the_provider_redirect_url(): void
    {
        $this->getJson('/api/v1/auth/social/fake')
            ->assertOk()
            ->assertJsonPath('data.redirect_url', 'https://provider.example/oauth');
    }

    public function test_the_callback_answers_json_to_api_clients(): void
    {
        $response = $this->getJson('/api/v1/auth/social/fake/callback?code=ok');

        $response->assertOk()
            ->assertJsonPath('data.user.id', $this->user->id)
            ->assertJsonPath('data.user.email', 'ada@example.com')
            ->assertJsonPath('data.redirect_url', '/dashboard?token='.$response->json('data.token'));

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_the_callback_redirects_browsers_to_the_spa_with_the_token(): void
    {
        config([
            'security.frontend.url' => 'http://localhost:5175',
            'socialite.frontend_redirect' => '/auth/callback',
        ]);

        $response = $this->get('/api/v1/auth/social/fake/callback?code=ok', ['Accept' => 'text/html']);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');

        $this->assertStringStartsWith('http://localhost:5175/auth/callback#token=', $location);

        $token = substr($location, strpos($location, 'token=') + 6);

        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('data.id', (string) $this->user->id);
    }

    public function test_the_callback_keeps_json_when_the_spa_redirect_is_disabled(): void
    {
        config(['socialite.frontend_redirect' => '']);

        $this->get('/api/v1/auth/social/fake/callback?code=ok', ['Accept' => 'text/html'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $this->user->id);
    }

    public function test_an_unknown_provider_is_not_found(): void
    {
        $this->getJson('/api/v1/auth/social/nope')
            ->assertNotFound()
            ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
    }

    private function driver(): SocialiteDriver
    {
        $user = $this->user;

        return new class($user) implements SocialiteDriver
        {
            public function __construct(private readonly User $user) {}

            public function key(): string
            {
                return 'fake';
            }

            public function name(): string
            {
                return 'Fake';
            }

            public function isEnabled(): bool
            {
                return true;
            }

            public function getRedirectUrl(): string
            {
                return 'https://provider.example/oauth';
            }

            public function handleCallback(): SocialiteUser
            {
                return new class implements SocialiteUser
                {
                    public function getId(): string
                    {
                        return 'social-1';
                    }

                    public function getNickname(): ?string
                    {
                        return 'ada';
                    }

                    public function getName(): string
                    {
                        return 'Ada Lovelace';
                    }

                    public function getEmail(): string
                    {
                        return 'ada@example.com';
                    }

                    public function getAvatar(): ?string
                    {
                        return null;
                    }
                };
            }

            public function findOrCreateUser(SocialiteUser $socialiteUser): Authenticatable
            {
                return $this->user;
            }

            public function getConfigKey(): string
            {
                return 'fake';
            }

            public function getScopes(): array
            {
                return [];
            }
        };
    }
}
