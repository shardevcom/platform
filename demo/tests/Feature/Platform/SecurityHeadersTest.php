<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-CASA-018 acceptance: security headers on every API response, and COEP
 * deliberately absent. CSP only where HTML exists, never on JSON APIs.
 */
final class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        config(['security.headers.hsts.emit_http' => false]);
    }

    public function test_admin_api_carries_the_expected_headers_without_coep(): void
    {
        $plain = AdminUser::factory()->create()->createToken('cli', [
            'products.view', 'products.list',
        ])->accessToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$plain)->getJson('/admin/api/v1/products');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->assertHeader('Cross-Origin-Resource-Policy', 'same-site');

        // COEP require-corp breaks JSON consumers: must never be present.
        $response->assertHeaderMissing('Cross-Origin-Embedder-Policy');
        $response->assertHeaderMissing('Cross-Origin-Opener-Policy-Worker');
    }

    public function test_headers_are_present_on_public_and_admin_paths_even_when_unauthenticated(): void
    {
        foreach (['/admin/api/v1/products', '/api/v1/products'] as $uri) {
            $response = $this->getJson($uri);
            $response->assertUnauthorized();
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader('X-Frame-Options', 'DENY');
            $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
            $response->assertHeaderMissing('Cross-Origin-Embedder-Policy');
        }
    }

    public function test_hsts_is_only_emitted_over_https_unless_explicitly_forced(): void
    {
        $this->getJson('/admin/api/v1/products')->assertHeaderMissing('Strict-Transport-Security');

        config(['security.headers.hsts.emit_http' => true]);

        $this->getJson('/admin/api/v1/products')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_csp_is_applied_to_html_but_never_to_a_json_api(): void
    {
        $html = $this->get('/');
        $html->assertSee('Laravel');
        $html->assertHeader('Content-Security-Policy');

        $policy = \Illuminate\Support\Str::of((string) $html->headers->get('Content-Security-Policy'));
        $this->assertTrue($policy->contains("default-src 'self'"), 'CSP default-src debe ser \'self\'');
        $this->assertTrue($policy->contains("frame-ancestors 'none'"));

        $this->getJson('/admin/api/v1/products')->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_headers_can_be_disabled_entirely(): void
    {
        config(['security.headers.enabled' => false]);

        $this->getJson('/admin/api/v1/products')
            ->assertHeaderMissing('X-Content-Type-Options')
            ->assertHeaderMissing('X-Frame-Options');
    }
}