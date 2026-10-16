<?php
declare(strict_types=1);
namespace Tests\Feature\Platform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
final class TmpBugTest extends TestCase
{
    use RefreshDatabase;
    public function test_count_is_the_page_size_not_the_key_count(): void
    {
        $plain = \App\Models\User::factory()->create()->createToken('cli', ['products.*'])->accessToken;
        $this->withHeader('Authorization', 'Bearer '.$plain);
        foreach (['A', 'B', 'C'] as $n => $s) {
            $this->postJson('/graphql', ['query' => "mutation { createProduct(input: {sku: \"X-$s\", name: \"N$n\"}) { id } }"]);
        }
        $r = $this->postJson('/graphql', ['query' => '{ products(per_page: 2) { pagination { total count per_page } data { sku } } }']);
        echo "\nPAGINATION: ".$r->getContent()."\n";
        $this->assertTrue(true);
    }
    public function test_delete_only_needs_the_delete_ability(): void
    {
        $plain = \App\Models\User::factory()->create()->createToken('cli', ['products.view','products.create','products.update'])->accessToken;
        $this->withHeader('Authorization', 'Bearer '.$plain);
        $this->postJson('/graphql', ['query' => 'mutation { createProduct(input: {sku: "D-1", name: "One"}) { id } }']);
        // now a token that can delete but NOT view
        $d = \App\Models\User::factory()->create()->createToken('cli', ['products.delete'])->accessToken;
        $r = $this->postJson('/graphql', ['query' => 'mutation { deleteProduct(id: 1) }']);
        echo "\nDELETE WITHOUT VIEW: ".$r->getContent()."\n";
        $this->assertTrue(true);
    }
    public function test_brand_relation(): void
    {
        $plain = \App\Models\User::factory()->create()->createToken('cli', ['products.*'])->accessToken;
        $this->withHeader('Authorization', 'Bearer '.$plain);
        $this->postJson('/graphql', ['query' => 'mutation { createProduct(input: {sku: "B-1", name: "One"}) { id } }']);
        $r = $this->postJson('/graphql', ['query' => '{ product(id: 1) { sku brand { id name } } }']);
        echo "\nBRAND: ".$r->getContent()."\n";
        $this->assertTrue(true);
    }
}
