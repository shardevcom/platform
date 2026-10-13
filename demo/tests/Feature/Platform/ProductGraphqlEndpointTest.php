<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-073/T-074 over real HTTP.
 *
 * The whole GraphQL layer used to be dead: the schema leaned on Lighthouse's
 * @paginate/@find/@create/@delete directives, which talk to Eloquent directly
 * and never reach a use case. Every query returned an internal error and the
 * provider bound resolver classes that did not exist. These are full
 * request/response round trips through /graphql.
 */
final class ProductGraphqlEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    private const ALL_ABILITIES = ['products.view', 'products.create', 'products.update', 'products.delete', 'products.list', 'products.import', 'products.export'];

    public function test_a_token_holder_can_drive_the_whole_cycle_through_graphql(): void
    {
        $this->token();

        $this->graphql('mutation { createProduct(input: {sku: "GQL-1", name: "Widget"}) { id sku name } }')
            ->assertJsonPath('data.createProduct.sku', 'GQL-1');

        $this->graphql('{ products { data { sku } pagination { total per_page } } }')
            ->assertJsonPath('data.products.data.0.sku', 'GQL-1')
            ->assertJsonPath('data.products.pagination.total', 1);

        $this->graphql('{ product(id: 1) { sku name } }')
            ->assertJsonPath('data.product.name', 'Widget');

        $this->graphql('mutation { updateProduct(id: 1, input: {name: "Widget II"}) { name } }')
            ->assertJsonPath('data.updateProduct.name', 'Widget II');

        $this->graphql('mutation { deleteProduct(id: 1) }')
            ->assertJsonPath('data.deleteProduct', true);
    }

    public function test_a_graphql_wildcard_grant_authorizes_every_action(): void
    {
        // The demo's ContextPermissionResolver used to do an exact in_array on
        // `products.view`, so a token holding `products.*` was denied everything.
        $this->token(['products.*']);

        $this->graphql('mutation { createProduct(input: {sku: "W-1", name: "Wildcard"}) { id } }')
            ->assertJsonPath('data.createProduct.id', '1');
    }

    public function test_a_guest_is_refused_with_the_domain_error_code(): void
    {
        $this->graphql('{ products { data { id } } }')
            ->assertJsonPath('errors.0.extensions.code', 'FORBIDDEN');
    }

    public function test_a_token_without_the_ability_is_refused_for_that_action(): void
    {
        $this->token(['products.view']);

        $this->graphql('{ products { data { id } } }')->assertJsonMissingPath('errors');

        $this->graphql('mutation { createProduct(input: {sku: "N-1", name: "No"}) { id } }')
            ->assertJsonPath('errors.0.extensions.code', 'FORBIDDEN')
            ->assertJsonPath('errors.0.extensions.details.action', 'create');
    }

    public function test_a_missing_row_reports_the_same_code_rest_does(): void
    {
        $this->token();

        $this->graphql('{ product(id: 999) { id } }')
            ->assertJsonPath('errors.0.extensions.code', 'RESOURCE_NOT_FOUND')
            ->assertJsonPath('errors.0.extensions.details.id', '999');
    }

    public function test_an_undeclared_filter_is_rejected_by_the_use_case_not_the_resolver(): void
    {
        $this->token();

        $this->graphql('{ products(filter: [{field: "secret_col", value: 1}]) { data { id } } }')
            ->assertJsonPath('errors.0.extensions.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['errors' => [['extensions' => ['code', 'details']]]]);
    }

    public function test_a_declared_filter_is_applied_by_the_same_repository(): void
    {
        $this->token();

        $this->graphql('mutation { createProduct(input: {sku: "F-1", name: "Keep"}) { id } }');
        $this->graphql('mutation { createProduct(input: {sku: "F-2", name: "Drop"}) { id } }');

        $this->graphql('{ products(filter: [{field: "sku", value: "F-1"}]) { pagination { total } data { sku } } }')
            ->assertJsonPath('data.products.pagination.total', 1)
            ->assertJsonPath('data.products.data.0.sku', 'F-1');
    }

    public function test_oversized_page_size_is_clamped_rather_than_refused(): void
    {
        $this->token();

        $this->graphql('{ products(per_page: 9999) { pagination { per_page } } }')
            ->assertJsonPath('data.products.pagination.per_page', 100);
    }

    public function test_an_undeclared_include_is_refused(): void
    {
        $this->token();

        $this->graphql('{ products(include: ["relacionNoDeclarada"]) { data { id } } }')
            ->assertJsonPath('errors.0.extensions.code', 'VALIDATION_FAILED');
    }

    public function test_a_declared_sort_reaches_the_repository(): void
    {
        $this->token();

        $this->graphql('mutation { createProduct(input: {sku: "S-1", name: "Alpha"}) { id } }');
        $this->graphql('mutation { createProduct(input: {sku: "S-2", name: "Beta"}) { id } }');

        $this->graphql('{ products(sort: [{field: "name", direction: desc}]) { data { sku } } }')
            ->assertJsonPath('data.products.data.0.sku', 'S-2');
    }

    public function test_a_duplicate_sku_is_a_conflict_and_not_a_crash(): void
    {
        $this->token();

        $this->graphql('mutation { createProduct(input: {sku: "D-1", name: "First"}) { id } }');

        $this->graphql('mutation { createProduct(input: {sku: "D-1", name: "Second"}) { id } }')
            ->assertJsonPath('errors.0.extensions.code', 'CONFLICT');
    }

    public function test_an_invalid_mutation_payload_reports_the_field_errors(): void
    {
        $this->token();

        // A GraphQL input validation error carries errors[].extensions.validation
        // rather than extensions.code: the request never reached a use case.
        $this->graphql('mutation { createProduct(input: {sku: "", name: "x"}) { id } }')
            ->assertJsonPath('errors.0.message', 'Validation failed for the field [createProduct].')
            ->assertJsonStructure(['errors' => [['extensions' => ['validation']]]]);
    }

    public function test_an_unknown_field_is_a_graphql_validation_error_not_a_crash(): void
    {
        $this->token();

        $this->graphql('{ products { data { noSuchField } } }')
            ->assertJsonPath('errors.0.message', 'Cannot query field "noSuchField" on type "Product".');
    }

    /**
     * T-CASA-015 parity: the graphql twin of /products/select runs the same
     * fixed projection. A field outside it is null on the wire, never leaked.
     */
    public function test_select_projection_matches_the_rest_endpoint(): void
    {
        $this->token();

        $this->graphql('mutation { createProduct(input: {sku: "GS-1", name: "Widget", ean: "098765"}) { id } }');

        $this->graphql('{ productsSelect { data { sku ean } } }')
            ->assertJsonPath('data.productsSelect.data.0.sku', 'GS-1')
            ->assertJsonPath('data.productsSelect.data.0.ean', null);
    }

    public function test_select_rejects_fields_outside_the_projection_from_graphql_too(): void
    {
        $this->token();

        $this->graphql('{ productsSelect(fields: ["brand_id"]) { data { id } } }')
            ->assertJsonPath('errors.0.extensions.code', 'VALIDATION_FAILED');
    }

    public function test_select_without_the_list_ability_is_forbidden_from_graphql_too(): void
    {
        $this->token(['products.view']);

        $this->graphql('{ productsSelect { data { id } } }')
            ->assertJsonPath('errors.0.extensions.code', 'FORBIDDEN');
    }

    /**
     * @param list<string> $abilities
     */
    private function token(array $abilities = self::ALL_ABILITIES): void
    {
        $plain = User::factory()->create()->createToken('cli', $abilities)->accessToken;

        $this->withHeader('Authorization', 'Bearer '.$plain);
    }

    /**
     * @return \Illuminate\Testing\TestResponse
     */
    private function graphql(string $query)
    {
        return $this->postJson('/graphql', ['query' => $query]);
    }
}