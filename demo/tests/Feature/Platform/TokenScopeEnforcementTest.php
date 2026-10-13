<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Shardevcom\Product\Models\Product;
use Tests\TestCase;

/**
 * T-CASA-008 acceptance, exercised over real HTTP: a token is scoped to the
 * exact actions it was granted. 'products.view' proves identity for reads but
 * must NOT open the sensitive operations 'products.import'/'products.export'.
 */
final class TokenScopeEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_view_only_token_can_read_but_never_import_nor_export(): void
    {
        $product = Product::factory()->create();
        $this->token(['products.view']);

        // products.view does what it was granted…
        $this->getJson('/admin/api/v1/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.sku', $product->sku);

        // …and nothing else: creating is also refused with the same envelope.
        $this->postJson('/admin/api/v1/products', ['sku' => 'NOPE', 'name' => 'Nope'])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');

        $file = UploadedFile::fake()->createWithContent('import.csv', "sku,name\nS-X,One\n");

        // Neither bulk ingestion nor bulk extraction inherit from the view
        // ability, even though both are reached through the same resource.
        $this->post('/admin/api/v1/products/import', ['file' => $file, 'format' => 'csv'])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->get('/admin/api/v1/products/export')
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_a_token_cannot_use_an_action_it_was_never_given_even_when_it_owns_the_resource(): void
    {
        // 'products.view' + sibling read abilities, no writable/import actions.
        $this->token(['products.view', 'products.list']);

        $file = UploadedFile::fake()->createWithContent('import.csv', "sku,name\nS-Y,Two\n");

        $this->post('/admin/api/v1/products/import', ['file' => $file, 'format' => 'csv'])
            ->assertForbidden();

        $this->get('/admin/api/v1/products/export')
            ->assertForbidden();
    }

    /**
     * @param list<string> $abilities
     */
    private function token(array $abilities): void
    {
        $plain = AdminUser::factory()->create()->createToken('cli', $abilities)->accessToken;

        $this->withHeader('Authorization', 'Bearer '.$plain);
    }
}