<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Platform\Authentication\PassportTokenResolver;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Shardevcom\Core\Authentication\BearerTokenAuthentication;
use Shardevcom\Core\Authentication\TokenRecord;
use Shardevcom\Core\Contracts\AuthenticationProvider;
use Shardevcom\Core\Contracts\TokenResolver;
use Shardevcom\Core\Exceptions\Unauthorized;
use Tests\TestCase;

/**
 * T-090 against the real Passport store: the Core rules and the storage seam
 * wired to something that actually keeps tokens in a database.
 *
 * Passport differs from the previous store in one way that shows below: a token
 * is a self-verifying signed JWT, so the store can reject an expired or revoked
 * one by itself and hands the caller "unknown". A row whose expires_at was
 * edited after issuance still lands on BearerTokenAuthentication, because the
 * claim inside the JWT does not move when the row does.
 */
final class PassportAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_container_wires_the_passport_store_behind_the_core_contract(): void
    {
        $this->assertInstanceOf(PassportTokenResolver::class, $this->app->make(TokenResolver::class));
        $this->assertInstanceOf(BearerTokenAuthentication::class, $this->app->make(AuthenticationProvider::class));
    }

    public function test_a_freshly_issued_token_produces_an_identity(): void
    {
        $user = User::factory()->create();
        $plain = $user->createToken('iPhone 15', ['products.create', 'products.view'])->accessToken;

        $identity = $this->app->make(AuthenticationProvider::class)->identityFromToken($plain);

        $this->assertSame((string) $user->id, $identity->id);
        $this->assertSame('User', $identity->kind);
        $this->assertSame(['products.create', 'products.view'], $identity->attributes['abilities']);
        $this->assertSame('iPhone 15', $identity->attributes['device']['name']);
        $this->assertSame('bearer', $identity->attributes['via']);
    }

    public function test_the_plain_token_is_never_stored(): void
    {
        $user = User::factory()->create();
        $plain = $user->createToken('laptop')->accessToken;

        $stored = Passport::token()->query()->firstOrFail();

        $this->assertNotSame($plain, $stored->id, 'the credential is the signed JWT, not the row id');
        $this->assertStringNotContainsString(
            $plain,
            (string) json_encode($stored->getAttributes()),
            'nothing in the row may reproduce the bearer credential'
        );
    }

    public function test_an_unknown_token_is_unauthorized(): void
    {
        $this->expectException(Unauthorized::class);

        $this->app->make(AuthenticationProvider::class)->identityFromToken('not.a.real.token');
    }

    /**
     * The JWT inside the token was minted for a year, so it still validates;
     * only the row says otherwise. That is the point: without
     * BearerTokenAuthentication the record below would authenticate forever,
     * and Passport would never notice because it reads its own claim, not this
     * column.
     */
    public function test_an_expired_token_is_rejected_even_though_the_store_still_has_the_row(): void
    {
        $user = User::factory()->create();
        $issued = $user->createToken('stale');
        $issued->getToken()->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->assertSame(1, Passport::token()->query()->count(), 'the row survives; only the rule rejects it');
        $this->assertNotNull($this->resolve($issued->accessToken), 'the store still resolves it');

        $this->assertRejected($issued->accessToken, 'expired');
    }

    /**
     * Passport revokes by flagging the row, and the resource server refuses the
     * JWT before the store gets to describe it, so this arrives as "unknown".
     * The caller cannot tell it from a guessed token, which is the point.
     */
    public function test_a_revoked_token_is_unauthorized(): void
    {
        $user = User::factory()->create();
        $issued = $user->createToken('revoked-me');

        $issued->getToken()->forceFill(['revoked' => true])->save();

        $this->assertSame(1, Passport::token()->query()->count(), 'revocation is a flag, not a deletion');

        $this->assertRejected($issued->accessToken, 'unknown');
    }

    public function test_revoking_one_device_leaves_the_others_working(): void
    {
        $user = User::factory()->create();
        $phone = $user->createToken('phone');
        $laptop = $user->createToken('laptop');

        $phone->getToken()->forceFill(['revoked' => true])->save();

        $this->assertNull($this->resolve($phone->accessToken));
        $this->assertSame('laptop', $this->resolve($laptop->accessToken)?->device['name']);
    }

    public function test_a_token_can_be_limited_to_one_ability(): void
    {
        $user = User::factory()->create();
        $kiosk = $user->createToken('kiosk', ['products.view']);

        $record = $this->resolve($kiosk->accessToken);

        $this->assertNotNull($record);
        $this->assertTrue($record->can('products.view'));
        $this->assertFalse($record->can('products.delete'), 'abilities are a real limit, not decoration');
    }

    /**
     * The stateless half of the contract: a Bearer request carries its own
     * proof — no cookie, no session, no CSRF token — and still authenticates
     * over real HTTP.
     */
    public function test_a_bearer_request_authenticates_without_a_session_or_csrf_token(): void
    {
        $user = User::factory()->create();
        $plain = $user->createToken('api-client')->accessToken;

        $this->app['router']
            ->get('/api/v1/_auth-probe', fn () => response()->json(['ok' => true]))
            ->middleware('auth:api');

        $this->getJson('/api/v1/_auth-probe', ['Authorization' => 'Bearer '.$plain])
            ->assertOk()
            ->assertJson(['ok' => true]);

        // No header means no identity, even though the user exists in the database.
        $this->assertGuest();

        $this->getJson('/api/v1/_auth-probe')->assertUnauthorized();
    }

    /**
     * The guard is asked for twice inside one test with two different tokens,
     * which is exactly the case a cache would get wrong: AuthManager keeps one
     * guard instance for the whole process.
     */
    public function test_two_requests_with_two_tokens_resolve_two_different_users(): void
    {
        $first = User::factory()->create()->createToken('first')->accessToken;
        $second = User::factory()->create()->createToken('second')->accessToken;

        $this->app['router']
            ->get('/api/v1/_whoami-probe', fn () => response()->json(['id' => auth()->id()]))
            ->middleware('auth:api');

        $a = $this->getJson('/api/v1/_whoami-probe', ['Authorization' => 'Bearer '.$first]);
        $b = $this->getJson('/api/v1/_whoami-probe', ['Authorization' => 'Bearer '.$second]);

        $a->assertOk();
        $b->assertOk();
        $this->assertNotSame($a->json('id'), $b->json('id'), 'the guard must not answer the second request with the first principal');
    }

    /**
     * ...and that holds only because the API route sits outside the web
     * middleware group. Asserted on the route definition because Laravel
     * disables CSRF while testing, so a 419 assertion would prove nothing.
     */
    public function test_the_api_path_is_outside_the_csrf_protected_web_group(): void
    {
        $api = $this->app['router']
            ->get('/api/v1/_csrf-probe', fn () => null)
            ->middleware('auth:api');

        $this->assertNotContains(ValidateCsrfToken::class, $this->expandedMiddleware($api));

        $web = $this->app['router']
            ->get('/_web-probe', fn () => null)
            ->middleware('web');

        $this->assertContains(ValidateCsrfToken::class, $this->expandedMiddleware($web));
    }

    /**
     * Route::gatherMiddleware() keeps group names verbatim, so 'web' has to be
     * expanded by hand to see what actually runs.
     *
     * The map is read from the HTTP kernel rather than the router because the
     * kernel only copies it across the first time it is resolved — which never
     * happens in a test that has not made a request yet, leaving the router
     * with an empty map and every group expanding to its own name.
     *
     * @return list<string>
     */
    private function expandedMiddleware(\Illuminate\Routing\Route $route): array
    {
        $groups = $this->app->make(\Illuminate\Foundation\Http\Kernel::class)->getMiddlewareGroups();
        $expanded = [];

        foreach ($route->gatherMiddleware() as $middleware) {
            $expanded = [...$expanded, ...($groups[$middleware] ?? [$middleware])];
        }

        return $expanded;
    }

    private function assertRejected(string $plain, string $expectedReason): void
    {
        try {
            $this->app->make(AuthenticationProvider::class)->identityFromToken($plain);
            $this->fail('the token must not authenticate');
        } catch (Unauthorized $e) {
            $this->assertSame('UNAUTHORIZED', $e->errorCode());
            $this->assertSame($expectedReason, $e->context()['reason']);
        }
    }

    private function resolve(string $plain): ?TokenRecord
    {
        return $this->app->make(TokenResolver::class)->resolve($plain);
    }
}
