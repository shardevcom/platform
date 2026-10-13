<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Shardevcom\Product\Models\Brand;
use Shardevcom\Product\Models\ProductImport;
use Tests\TestCase;

/**
 * T-120 acceptance, exercised over real HTTP.
 *
 * Every other product test builds a use case or repository by hand, so a
 * broken route, middleware or controller would pass the suite and still ship a
 * dead /api/v1/products. These are full request/response round trips.
 *
 * Uses admin platform routes (/admin/api/v1/...) with AdminUser tokens.
 */
final class ProductRestEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    private const ALL_ABILITIES = ['products.view', 'products.create', 'products.update', 'products.delete', 'products.list', 'products.import', 'products.export'];

    public function test_a_token_holder_can_drive_the_whole_crud_cycle_over_http(): void
    {
        $this->token();

        $create = $this->postJson('/admin/api/v1/products', ['sku' => 'HTTP-1', 'name' => 'Widget']);
        $create->assertCreated();
        $create->assertJsonPath('status', 'success');
        $create->assertJsonPath('data.sku', 'HTTP-1');

        $id = $create->json('data.id');

        $this->getJson("/admin/api/v1/products/{$id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Widget');

        $this->putJson("/admin/api/v1/products/{$id}", ['name' => 'Widget II'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Widget II');

        $this->patchJson("/admin/api/v1/products/{$id}", ['name' => 'Widget III'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Widget III');

        $this->getJson('/admin/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.0.sku', 'HTTP-1')
            ->assertJsonPath('meta.pagination.per_page', 15);

        $this->deleteJson("/admin/api/v1/products/{$id}")->assertOk();

        $this->getJson('/admin/api/v1/products')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_guest_is_rejected_with_the_shared_envelope(): void
    {
        $this->getJson('/admin/api/v1/products')
            ->assertUnauthorized()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'UNAUTHORIZED')
            ->assertJsonPath('data', null);
    }

    public function test_a_token_without_the_ability_is_refused_for_that_action_only(): void
    {
        $this->token(['products.view', 'products.list']);

        $this->postJson('/admin/api/v1/products', ['sku' => 'NOPE', 'name' => 'Nope'])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->getJson('/admin/api/v1/products')->assertOk();
    }

    public function test_validation_failures_reach_the_client_as_422_with_field_errors(): void
    {
        $this->token();

        $this->postJson('/admin/api/v1/products', ['sku' => '', 'name' => 'x'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonPath('status', 'error')
            ->assertJsonStructure(['meta' => ['details' => ['sku']]]);
    }

    public function test_a_missing_row_is_404_rather_than_an_empty_success(): void
    {
        $this->token();

        $this->getJson('/admin/api/v1/products/999999')
            ->assertNotFound()
            ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
    }

    public function test_a_duplicate_sku_is_a_conflict_and_not_a_500(): void
    {
        $this->token();

        $this->postJson('/admin/api/v1/products', ['sku' => 'DUP-HTTP', 'name' => 'First'])->assertCreated();

        $this->postJson('/admin/api/v1/products', ['sku' => 'DUP-HTTP', 'name' => 'Second'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CONFLICT');
    }

    public function test_an_undeclared_filter_is_422_and_never_reaches_the_query(): void
    {
        $this->token();

        $this->getJson('/admin/api/v1/products?filter[secret_column]=1')
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED');
    }

    public function test_listing_declares_the_total_and_honours_per_page(): void
    {
        $this->token();

        for ($i = 1; $i <= 3; $i++) {
            $this->postJson('/admin/api/v1/products', ['sku' => "P-{$i}", 'name' => "Product {$i}"])->assertCreated();
        }

        $this->getJson('/admin/api/v1/products?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination.total', 3)
            ->assertJsonPath('meta.pagination.last_page', 2);
    }

    public function test_an_oversized_page_size_is_clamped_rather_than_refused(): void
    {
        $this->token();

        $this->getJson('/admin/api/v1/products?per_page=9999')
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 100);
    }

    public function test_the_api_version_middleware_actually_runs(): void
    {
        $this->token();

        // The middleware alias is registered with Router::aliasMiddleware(); if
        // anyone reverts it to Router::middleware(), this header disappears and
        // the route 500s on every request.
        $this->getJson('/admin/api/v1/products')
            ->assertOk()
            ->assertHeader('API-Version', 'v1.0')
            ->assertHeader('Link', '</docs/api>; rel="describedby"');
    }

    /**
     * T-CASA-015: the select projection is a fixed allow-list. The row has to
     * be exactly {id, sku, name}; ean never leaves through this endpoint.
     */
    public function test_select_answers_exactly_the_declared_projection(): void
    {
        $this->token(['products.list', 'products.create']);

        $this->postJson('/admin/api/v1/products', ['sku' => 'SEL-1', 'name' => 'Widget', 'ean' => '123456'])->assertCreated();

        $response = $this->getJson('/admin/api/v1/products/select');

        $response->assertOk();
        $this->assertSame(['id', 'sku', 'name'], array_keys($response->json('data.0')));
        $response->assertJsonPath('data.0.sku', 'SEL-1');
        $response->assertJsonMissingPath('data.0.ean');
        $response->assertJsonMissingPath('data.0.brand_id');
    }

    /**
     * T-CASA-015: asking for a column outside the projection is a validation
     * error, not a silent drop — an attempt to read past minimisation is
     * visible in the response.
     */
    public function test_select_rejects_requested_fields_outside_the_projection(): void
    {
        $this->token(['products.list']);

        $response = $this->getJson('/admin/api/v1/products/select?fields=brand_id');

        $response->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED');

        // The validation envelope keys an error like "fields.brand_id" with an
        // embedded dot, so it is read from the payload instead of assertJsonPath.
        $details = $response->json('meta.details');
        $this->assertSame('Field is not part of the [list] projection.', $details['fields.brand_id'][0]);
    }

    public function test_select_allows_narrowing_inside_the_projection(): void
    {
        $this->token(['products.list', 'products.create']);

        $this->postJson('/admin/api/v1/products', ['sku' => 'SEL-2', 'name' => 'Narrow'])->assertCreated();

        $response = $this->getJson('/admin/api/v1/products/select?fields=name');

        $response->assertOk();
        $this->assertSame(['name'], array_keys($response->json('data.0')));
        $response->assertJsonPath('data.0.name', 'Narrow');
    }

    public function test_select_without_the_list_ability_is_forbidden(): void
    {
        $this->token(['products.view']);

        $this->getJson('/admin/api/v1/products/select')
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_select_keeps_declared_includes_next_to_the_projection(): void
    {
        $this->token(['products.list', 'products.create']);

        $brand = Brand::factory()->create(['name' => 'Acme']);

        $this->postJson('/admin/api/v1/products', [
            'sku' => 'SEL-3',
            'name' => 'WithBrand',
            'brand_id' => $brand->id,
        ])->assertCreated();

        $response = $this->getJson('/admin/api/v1/products/select?include=brand');

        $response->assertOk();
        $this->assertSame(['id', 'sku', 'name', 'brand'], array_keys($response->json('data.0')));
        $response->assertJsonPath('data.0.brand.name', 'Acme');
    }

    public function test_export_without_the_export_ability_is_forbidden(): void
    {
        // Holding products.view must NOT grant bulk extraction.
        $this->token(['products.view', 'products.list', 'products.import']);

        $this->get('/admin/api/v1/products/export')
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_export_answers_a_downloadable_file(): void
    {
        $this->token();

        $this->postJson('/admin/api/v1/products', ['sku' => 'EXP-1', 'name' => 'Exportable'])->assertCreated();

        $response = $this->get('/admin/api/v1/products/export');

        $response->assertOk();
        $response->assertDownload('products.xlsx');
        $this->assertStringContainsString('spreadsheet', (string) $response->headers->get('content-type'));
    }

    public function test_export_streams_the_whole_result_set_not_a_page(): void
    {
        $this->token();

        // More rows than the 15-row per_page default: an export that silently
        // paginated would only ship the first page.
        for ($i = 1; $i <= 20; $i++) {
            $this->postJson('/admin/api/v1/products', ['sku' => "SET-{$i}", 'name' => "Set {$i}"])->assertCreated();
        }

        $response = $this->get('/admin/api/v1/products/export?format=csv');

        $response->assertOk();
        $response->assertDownload('products.csv');

        $file = $response->baseResponse->getFile();
        $body = (string) file_get_contents($file->getPathname());

        $this->assertSame(
            20,
            collect(explode("\n", $body))->filter(static fn (string $line): bool => str_contains($line, 'SET-'))->count(),
        );
    }

    public function test_import_without_the_import_ability_is_forbidden(): void
    {
        $this->token(['products.view', 'products.list', 'products.export']);

        $file = UploadedFile::fake()->createWithContent('import.csv', "sku,name\nS-X,One\n");

        $this->post('/admin/api/v1/products/import', ['file' => $file, 'format' => 'csv'])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_import_queues_a_session_and_traces_each_physical_row(): void
    {
        $this->token();

        $file = UploadedFile::fake()->createWithContent('import.csv', "sku,name\nI-1,One\nI-2,Two\n");

        $response = $this->post('/admin/api/v1/products/import', ['file' => $file, 'format' => 'csv']);

        $response->assertStatus(202);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.status', 'queued');

        $importId = $response->json('data.import_id');

        // The 202 must not depend on reading the file: the response carries no
        // total_rows, the pre-flight escanea in the queue fills it afterwards.
        $response->assertJsonMissingPath('data.total_rows');

        $session = ProductImport::findOrFail($importId);
        $this->assertSame(2, $session->total_rows);

        // The test queue is sync, so the whole pipeline already ran: the chunk
        // batch AND its finalizer. A finished session proves the batch "finally"
        // callback actually ran (it cannot be left in "processing" anymore).
        $this->assertSame('completed', $session->status);

        // Products exist and every trace row carries the PHYSICAL spreadsheet
        // row (header = 1, first data row = 2).
        $this->assertDatabaseHas('products', ['sku' => 'I-1']);
        $this->assertDatabaseHas('products', ['sku' => 'I-2']);

        $rows = $session->rows()->orderBy('row_number')->get();
        $this->assertSame([2, 3], $rows->pluck('row_number')->all());
        $this->assertSame(['success', 'success'], $rows->pluck('status')->all());
    }

    public function test_import_validates_headers_off_the_request_and_marks_the_session_failed(): void
    {
        $this->token();

        // Column 'name' is missing: the async pre-flight rejects it without
        // importing a single product.
        $file = UploadedFile::fake()->createWithContent('import.csv', "sku\nI-1\n");

        $response = $this->post('/admin/api/v1/products/import', ['file' => $file, 'format' => 'csv']);

        $response->assertStatus(202);

        $session = ProductImport::findOrFail($response->json('data.import_id'));

        $this->assertSame('failed', $session->status);
        $this->assertSame('Missing required columns: name', $session->error_summary['message']);
        $this->assertDatabaseMissing('products', ['sku' => 'I-1']);
    }

    public function test_import_status_requires_the_import_ability(): void
    {
        $this->token(['products.view', 'products.list', 'products.export']);

        $this->getJson('/admin/api/v1/products/import/any-id/status')
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_import_rejects_a_file_over_the_size_limit_with_413(): void
    {
        $this->token();

        $content = "sku,name\nA-1,One with a long name that pushes this file past its limit\nA-2,Two\n";
        config(['platform.import.max_file_size' => strlen($content) - 1]);

        $file = UploadedFile::fake()->createWithContent('import.csv', $content);

        $this->post('/admin/api/v1/products/import', ['file' => $file, 'format' => 'csv'])
            ->assertStatus(413)
            ->assertJsonPath('code', 'REQUEST_ENTITY_TOO_LARGE')
            ->assertJsonPath('data', null);
    }

    public function test_import_rejects_an_unknown_declared_format_with_415(): void
    {
        $this->token();

        $file = UploadedFile::fake()->createWithContent('import.exe', "PK\x03\x04");

        $this->post('/admin/api/v1/products/import', ['file' => $file, 'format' => 'exe'])
            ->assertStatus(415)
            ->assertJsonPath('code', 'UNSUPPORTED_MEDIA_TYPE');
    }

    public function test_import_rejects_a_zip_renamed_as_xlsx_with_415(): void
    {
        $this->token();

        $zipPath = sys_get_temp_dir().'/import-test-'.uniqid().'.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('readme.txt', 'I am a plain zip archive, not a spreadsheet.');
        $zip->close();

        $file = UploadedFile::fake()->createWithContent(
            'products.xlsx',
            (string) file_get_contents($zipPath),
        );
        unlink($zipPath);

        $this->post('/admin/api/v1/products/import', ['file' => $file, 'format' => 'xlsx'])
            ->assertStatus(415)
            ->assertJsonPath('code', 'UNSUPPORTED_MEDIA_TYPE');
    }

    public function test_import_rejects_a_spreadsheet_with_macros_with_422(): void
    {
        $this->token();

        $xlsmPath = sys_get_temp_dir().'/import-test-'.uniqid().'.zip';
        $zip = new \ZipArchive();
        $zip->open($xlsmPath, \ZipArchive::CREATE);
        $zip->addFromString('xl/workbook.xml', '<workbook/>');
        $zip->addFromString('vbaProject.bin', 'macro bytes');
        $zip->close();

        $file = UploadedFile::fake()->createWithContent(
            'products.xlsm',
            (string) file_get_contents($xlsmPath),
        );
        unlink($xlsmPath);

        $this->post('/admin/api/v1/products/import', ['file' => $file, 'format' => 'xlsx'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'BUSINESS_RULE_VIOLATION');
    }

    public function test_import_rejects_formula_cells_with_422_and_never_imports(): void
    {
        $this->token();

        $file = UploadedFile::fake()->createWithContent(
            'import.csv',
            "sku,name\n=cmd|'/C calc',Evil\nI-2,Good\n",
        );

        $this->post('/admin/api/v1/products/import', ['file' => $file, 'format' => 'csv'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'BUSINESS_RULE_VIOLATION');

        // The formula was rejected before a single row was queued: nothing
        // was executed and nothing was imported.
        $this->assertDatabaseMissing('products', ['sku' => 'I-2']);
    }

    public function test_import_rejects_an_excessive_row_count_off_the_queue(): void
    {
        $this->token();

        config(['platform.import.max_rows' => 2]);

        $file = UploadedFile::fake()->createWithContent(
            'import.csv',
            "sku,name\nA-1,One\nA-2,Two\nA-3,Three\n",
        );

        $response = $this->post('/admin/api/v1/products/import', ['file' => $file, 'format' => 'csv'])
            ->assertStatus(202);

        $session = ProductImport::findOrFail($response->json('data.import_id'));

        $this->assertSame('failed', $session->status);
        $this->assertSame('Row count 3 exceeds the maximum of 2 rows.', $session->error_summary['message']);
        $this->assertDatabaseMissing('products', ['sku' => 'A-1']);
    }

    public function test_import_session_status_and_errors_are_scoped_to_their_creator(): void
    {
        $owner = AdminUser::factory()->create();
        $this->withHeader('Authorization', 'Bearer '.$owner->createToken('cli', self::ALL_ABILITIES)->accessToken);

        $file = UploadedFile::fake()->createWithContent('import.csv', "sku,name\nS-SCOPED,One\n");
        $response = $this->post('/admin/api/v1/products/import', ['file' => $file, 'format' => 'csv'])
            ->assertStatus(202);

        $importId = $response->json('data.import_id');

        // The creator reads its own session.
        $this->getJson('/admin/api/v1/products/import/'.$importId.'/status')
            ->assertOk()
            ->assertJsonPath('data.import_id', $importId);

        $this->getJson('/admin/api/v1/products/import/'.$importId.'/errors')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Another user WITH the same import ability cannot read it: ownership,
        // not permission, is the boundary, and the answer is 404 so the read
        // path never reveals whether a session id exists.
        $this->withHeader('Authorization', 'Bearer '.AdminUser::factory()->create()->createToken('cli', self::ALL_ABILITIES)->accessToken);

        $this->getJson('/admin/api/v1/products/import/'.$importId.'/status')
            ->assertNotFound()
            ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

        $this->getJson('/admin/api/v1/products/import/'.$importId.'/errors')
            ->assertNotFound()
            ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
    }

    /**
     * Issue a token with the given abilities and authenticate later requests.
     *
     * @param list<string> $abilities
     */
    private function token(array $abilities = self::ALL_ABILITIES): void
    {
        $plain = AdminUser::factory()->create()->createToken('cli', $abilities)->accessToken;

        $this->withHeader('Authorization', 'Bearer '.$plain);
    }
}