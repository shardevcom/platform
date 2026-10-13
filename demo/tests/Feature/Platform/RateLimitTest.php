<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * T-CASA-020 acceptance, exercised over real HTTP.
 *
 * RateLimitMiddleware runs pre-auth on every platform, so anonymous requests
 * are counted by IP and authenticated requests by user; import/export get an
 * additional bucket scoped by user AND tenant.
 */
final class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    private const ALL_ABILITIES = ['products.view', 'products.create', 'products.update', 'products.delete', 'products.list', 'products.import', 'products.export'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['cache']->flush();
    }

    public function test_defaults_match_the_acceptance(): void
    {
        $this->assertSame(30, config('security.rate_limit.anonymous.limit'));
        $this->assertSame(120, config('security.rate_limit.authenticated.limit'));
        $this->assertTrue(config('security.rate_limit.enabled'));
        $this->assertArrayHasKey('products.import', config('security.rate_limit.operations'));
        $this->assertArrayHasKey('products.export', config('security.rate_limit.operations'));
    }

    public function test_the_31st_anonymous_request_is_throttled_by_ip(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $response = $this->getJson('/admin/api/v1/products');
            $response->assertUnauthorized();
            $response->assertHeader('X-RateLimit-Limit', '30');
        }

        // The 30th left remaining 0; the 31st exceeds the anonymous bucket.
        $throttled = $this->getJson('/admin/api/v1/products');

        $throttled->assertStatus(429)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'RATE_LIMITED')
            ->assertJsonPath('data', null)
            ->assertJsonPath('meta.details.limit', 30)
            ->assertJsonPath('meta.details.scope', 'anonymous:127.0.0.1');

        $this->assertNotEmpty($throttled->headers->get('Retry-After'));
        $this->assertNotEmpty($throttled->headers->get('X-RateLimit-Reset'));
        $throttled->assertHeader('X-RateLimit-Remaining', '0');
    }

    public function test_the_121st_authenticated_request_is_throttled_by_user(): void
    {
        $plain = AdminUser::factory()->create()->createToken('cli', self::ALL_ABILITIES)->accessToken;

        for ($i = 1; $i <= 120; $i++) {
            $this->withHeader('Authorization', 'Bearer '.$plain)->getJson('/admin/api/v1/products')->assertOk();
        }

        $this->withHeader('Authorization', 'Bearer '.$plain)->getJson('/admin/api/v1/products')
            ->assertStatus(429)
            ->assertJsonPath('code', 'RATE_LIMITED');
    }

    public function test_an_import_hits_its_own_bucket_before_a_plain_get(): void
    {
        $this->importOps(['limit' => 2, 'decay_seconds' => 60]);

        $this->token();

        // Two imports pass and are queued.
        $this->import()->assertStatus(202);
        $this->import()->assertStatus(202);

        // The third import is throttled while a plain GET is still well under
        // the 120/min user limit: the op bucket is stricter than the base one.
        $throttled = $this->import();
        $throttled->assertStatus(429)
            ->assertJsonPath('code', 'RATE_LIMITED')
            ->assertJsonPath('meta.details.scope', 'operation:products.import');

        $get = $this->getJson('/admin/api/v1/products');
        $get->assertOk();
        $get->assertHeader('X-RateLimit-Limit', '120');
        $this->assertTrue((int) $get->headers->get('X-RateLimit-Remaining') > 0);
    }

    public function test_operation_buckets_are_scoped_per_user_and_per_tenant(): void
    {
        $this->importOps(['limit' => 2, 'decay_seconds' => 60]);

        $user = AdminUser::factory()->create();
        $plain = $user->createToken('cli', self::ALL_ABILITIES)->accessToken;

        $this->withHeader('Authorization', 'Bearer '.$plain)->withHeader('X-Tenant-ID', 'A');

        $this->import()->assertStatus(202);
        $this->import()->assertStatus(202);

        // Same user, other tenant: its own counter, nothing shared.
        $this->withHeader('X-Tenant-ID', 'B');
        $this->import()->assertStatus(202);

        // Back to tenant A the user has exhausted its import quota.
        $this->withHeader('X-Tenant-ID', 'A');
        $this->import()->assertStatus(429);

        // A different user on the same over-quota tenant starts clean.
        $other = AdminUser::factory()->create()->createToken('cli', self::ALL_ABILITIES)->accessToken;
        $this->withHeader('Authorization', 'Bearer '.$other)->withHeader('X-Tenant-ID', 'A');
        $this->import()->assertStatus(202);
    }

    public function test_rate_limiting_can_be_disabled_entirely(): void
    {
        config(['security.rate_limit.enabled' => false]);

        $this->token();

        for ($i = 0; $i < 5; $i++) {
            $this->getJson('/admin/api/v1/products')->assertOk();
        }
    }

    private function import(): \Illuminate\Testing\TestResponse
    {
        $file = UploadedFile::fake()->createWithContent('import.csv', "sku,name\nR-1,One\n");

        return $this->post('/admin/api/v1/products/import', ['file' => $file, 'format' => 'csv']);
    }

    /**
     * Route names carry dots, which config()->set() would split into nested
     * keys, so the operations map must be replaced whole.
     *
     * @param array{limit:int,decay_seconds:int} $settings
     */
    private function importOps(array $settings): void
    {
        config(['security.rate_limit.operations' => ['products.import' => $settings]]);
    }

    /**
     * @param list<string> $abilities
     */
    private function token(array $abilities = self::ALL_ABILITIES): void
    {
        $plain = AdminUser::factory()->create()->createToken('cli', $abilities)->accessToken;

        $this->withHeader('Authorization', 'Bearer '.$plain);
    }
}