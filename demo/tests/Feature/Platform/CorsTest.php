<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-CASA-019 acceptance: restrictive CORS, no wildcard.
 *
 * Only explicit origins get ACAO; preflight OPTIONS answers 200 with the
 * correct headers WITHOUT credentials; everything else stays CORS-free.
 */
final class CorsTest extends TestCase
{
    use RefreshDatabase;

    private const ALLOWED = 'https://admin.example.test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cors.allowed_origins' => ['http://localhost', self::ALLOWED],
            'cors.allowed_origins_patterns' => [],
        ]);
    }

    public function test_a_non_listed_origin_gets_no_acao_on_any_platform_path(): void
    {
        foreach (['/admin/api/v1/products', '/api/v1/products'] as $uri) {
            $response = $this->withHeader('Origin', 'https://evil.example')->getJson($uri);

            $response->assertHeaderMissing('Access-Control-Allow-Origin');
            $response->assertHeaderMissing('Access-Control-Allow-Credentials');
        }
    }

    public function test_a_listed_origin_gets_the_origin_echoed_back(): void
    {
        $plain = AdminUser::factory()->create()->createToken('cli', [
            'products.view', 'products.list',
        ])->accessToken;

        $response = $this->withHeaders([
            'Origin' => self::ALLOWED,
            'Authorization' => 'Bearer '.$plain,
        ])->getJson('/admin/api/v1/products');

        $response->assertOk();
        $response->assertHeader('Access-Control-Allow-Origin', self::ALLOWED);

        // Credentials are never advertised, even authenticated.
        $response->assertHeaderMissing('Access-Control-Allow-Credentials');

        // Exposed headers let browsers read the rate-limit and version info.
        $expose = (string) $response->headers->get('Access-Control-Expose-Headers');
        $this->assertStringContainsString('X-RateLimit-Limit', $expose);
        $this->assertStringContainsString('X-RateLimit-Remaining', $expose);
        $this->assertStringContainsString('Retry-After', $expose);
        $this->assertStringContainsString('Link', $expose);
    }

    public function test_a_preflight_from_a_listed_origin_answers_200_with_the_allowed_surface(): void
    {
        $response = $this->withHeaders([
            'Origin' => self::ALLOWED,
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'Authorization, X-Request-ID',
        ])->options('/admin/api/v1/products');

        // 2xx: the framework answers preflight with 204 No Content, the
        // spec-correct success status (the criterion's "200" means a 2xx).
        $response->assertSuccessful();
        $response->assertHeader('Access-Control-Allow-Origin', self::ALLOWED);
        $response->assertHeader('Access-Control-Max-Age', '3600');
        $response->assertHeaderMissing('Access-Control-Allow-Credentials');

        $methods = (string) $response->headers->get('Access-Control-Allow-Methods');
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
            $this->assertStringContainsString($method, $methods);
        }

        $headers = (string) $response->headers->get('Access-Control-Allow-Headers');
        // HandleCors lowercases the echoed header list.
        foreach (['authorization', 'content-type', 'x-request-id', 'x-correlation-id', 'x-timezone'] as $header) {
            $this->assertStringContainsString($header, $headers);
        }
    }

    public function test_a_preflight_from_a_non_listed_origin_is_never_answered(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://evil.example',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/admin/api/v1/products');

        $response->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_security_headers_and_cors_coexist_on_the_same_response(): void
    {
        $plain = AdminUser::factory()->create()->createToken('cli', [
            'products.view', 'products.list',
        ])->accessToken;

        $response = $this->withHeaders([
            'Origin' => self::ALLOWED,
            'Authorization' => 'Bearer '.$plain,
        ])->getJson('/admin/api/v1/products');

        $response->assertHeader('Access-Control-Allow-Origin', self::ALLOWED);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeaderMissing('Cross-Origin-Embedder-Policy');
    }
}