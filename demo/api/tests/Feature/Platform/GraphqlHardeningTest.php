<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\User;
use GraphQL\Validator\Rules\DisableIntrospection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-CASA-022 acceptance: Lighthouse depth/complexity/introspection limits and
 * rate limiting of GraphQL mutations.
 */
final class GraphqlHardeningTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    private const ALL_ABILITIES = ['products.view', 'products.create', 'products.update', 'products.delete', 'products.list', 'products.import', 'products.export'];

    public function test_the_production_limits_are_the_declared_defaults(): void
    {
        $this->assertSame(1000, config('lighthouse.security.max_query_complexity'));
        $this->assertSame(15, config('lighthouse.security.max_query_depth'));
        $this->assertSame(100, config('lighthouse.pagination.max_count'));
        $this->assertSame(10, config('security.rate_limit.operations.graphql_mutation.limit'));

        // introspection stays reachable outside production…
        $this->assertSame(DisableIntrospection::DISABLED, config('lighthouse.security.disable_introspection'));
        // …and is disabled once the app runs in production (the ternary in the
        // provider flips it on env('APP_ENV')).
    }

    public function test_a_query_over_1000_complexity_is_rejected(): void
    {
        $this->token();

        // 400 aliased list selections: each costs ~3 (products+data+id), far
        // over the 1000 cap. Default per-field cost IS the complexity guard
        // (@cost/@complexity only change the per-field estimator).
        $aliases = [];
        for ($i = 0; $i < 400; $i++) {
            $aliases[] = "p{$i}: products { data { id } }";
        }

        $response = $this->graphql('{ '.implode(' ', $aliases).' }');

        $message = $response->json('errors.0.message');
        $this->assertIsString($message);
        $this->assertStringContainsStringIgnoringCase('complexity', $message);
        $this->assertStringContainsString('1000', $message);
    }

    public function test_a_query_deeper_than_15_is_rejected(): void
    {
        $this->token();

        // Introspection ghost types are recursive, so a valid query can nest
        // deeper than the product schema itself allows. 8 typed hops (depth 18)
        // exceed the 15 depth cap while staying a real query.
        $query = '{ __type(name: "Query") { '
            .str_repeat('fields { type { ', 8)
            .'name '.str_repeat('}', 18);

        $response = $this->graphql($query);

        $message = $response->json('errors.0.message');
        $this->assertIsString($message);
        $this->assertStringContainsStringIgnoringCase('depth', $message);
        $this->assertStringContainsString('15', $message);
    }

    public function test_introspection_is_rejected_when_switched_to_production_mode(): void
    {
        config(['lighthouse.security.disable_introspection' => DisableIntrospection::ENABLED]);

        $this->token();

        $response = $this->graphql('{ __schema { queryType { name } } }');

        $message = $response->json('errors.0.message');
        $this->assertIsString($message);
        $this->assertStringContainsStringIgnoringCase('introspection', $message);
    }

    public function test_mutations_are_rate_limited_per_user_and_tenant_with_a_stricter_bucket(): void
    {
        $this->mutationOps(['limit' => 2, 'decay_seconds' => 60]);

        $plain = User::factory()->create()->createToken('cli', self::ALL_ABILITIES)->accessToken;
        $this->withHeader('Authorization', 'Bearer '.$plain)->withHeader('X-Tenant-ID', 'A');

        $this->graphql('mutation { createProduct(input: {sku: "M-1", name: "One"}) { id } }')
            ->assertJsonPath('data.createProduct.id', '1');
        $this->graphql('mutation { createProduct(input: {sku: "M-2", name: "Two"}) { id } }')
            ->assertJsonPath('data.createProduct.id', '2');

        // Third mutation exceeds the 2/min bucket; a read query is unaffected.
        $this->graphql('mutation { createProduct(input: {sku: "M-3", name: "Three"}) { id } }')
            ->assertStatus(429)
            ->assertJsonPath('code', 'RATE_LIMITED')
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('meta.details.scope', 'operation:graphql_mutation');

        $this->graphql('{ products { pagination { total } } }')
            ->assertJsonPath('data.products.pagination.total', 2);

        // Another tenant of the same user starts with its own counter.
        $this->withHeader('X-Tenant-ID', 'B');
        $this->graphql('mutation { createProduct(input: {sku: "M-4", name: "Four"}) { id } }')
            ->assertJsonPath('data.createProduct.id', '3');
        $this->graphql('mutation { createProduct(input: {sku: "M-5", name: "Five"}) { id } }')
            ->assertJsonPath('data.createProduct.id', '4');
        $this->graphql('mutation { createProduct(input: {sku: "M-6", name: "Six"}) { id } }')
            ->assertStatus(429)
            ->assertJsonPath('code', 'RATE_LIMITED');
    }

    /**
     * Route/op keys carry text with dots or underscores that config()->set()
     * splits, so operation maps are replaced whole.
     *
     * @param array{limit:int,decay_seconds:int} $settings
     */
    private function mutationOps(array $settings): void
    {
        config(['security.rate_limit.operations' => ['graphql_mutation' => $settings]]);
    }

    /**
     * @param list<string> $abilities
     */
    private function token(array $abilities = self::ALL_ABILITIES): void
    {
        $plain = User::factory()->create()->createToken('cli', $abilities)->accessToken;

        $this->withHeader('Authorization', 'Bearer '.$plain);
    }

    private function graphql(string $query): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/graphql', ['query' => $query]);
    }
}