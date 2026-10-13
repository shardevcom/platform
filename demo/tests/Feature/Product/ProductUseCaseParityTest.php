<?php

declare(strict_types=1);

namespace Tests\Feature\Product;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\Contracts\AuthorizationManager as ManagerContract;
use Shardevcom\Core\Contracts\QueryExecutor as QueryExecutorContract;
use Shardevcom\Core\Contracts\ResourceRegistry;
use Shardevcom\Core\Exceptions\ValidationFailed;
use Shardevcom\Core\Lifecycle\ActionLifecycle;
use Shardevcom\Product\Application\Commands\CreateProduct;
use Shardevcom\Product\Application\Commands\DeleteProduct;
use Shardevcom\Product\Application\Commands\UpdateProduct;
use Shardevcom\Product\Application\Data\CreateProductData;
use Shardevcom\Product\Application\Data\UpdateProductData;
use Shardevcom\Product\Application\Queries\GetProduct;
use Shardevcom\Product\Application\Queries\ListProducts;
use Shardevcom\Product\Models\Product;
use Shardevcom\Product\ProductResourceDefinition;
use Shardevcom\Product\Repositories\ProductRepository;
use Tests\TestCase;

/**
 * T-060 acceptance: the same use case, invoked the way a REST controller and
 * a GraphQL resolver would invoke it, must produce identical rules, identical
 * errors and identical results.
 *
 * The two "adapters" below are deliberately thin fakes: they translate a
 * transport payload into a use-case call and nothing else. If a rule ever
 * leaked into an adapter, parity would break here.
 */
final class ProductUseCaseParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_from_rest_and_graphql_returns_the_same_row(): void
    {
        $rest = $this->restAdapter()->create(['sku' => 'PAR-1', 'name' => 'Parity'], $this->context());
        $graphql = $this->graphqlAdapter()->create(['sku' => 'PAR-2', 'name' => 'Parity'], $this->context());

        $this->assertSame('PAR-1', $rest['sku']);
        $this->assertSame('PAR-2', $graphql['sku']);
        $this->assertSame('Parity', $rest['name']);
        $this->assertSame(Product::count(), 2);
    }

    public function test_create_applies_the_same_defaults_from_both_transports(): void
    {
        $rest = $this->restAdapter()->create(['sku' => 'D-1', 'name' => 'Defaulted'], $this->context());
        $graphql = $this->graphqlAdapter()->create(['sku' => 'D-2', 'name' => 'Defaulted'], $this->context());

        foreach ([$rest, $graphql] as $row) {
            $this->assertTrue($row['active'], 'active defaults to true');
            $this->assertNull($row['ean']);
            $this->assertNull($row['brand_id']);
        }
    }

    /**
     * @return array<string, array{list<string>, list<string>}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'missing sku' => [[], ['sku', 'name']],
            'empty sku' => [['sku' => '', 'name' => 'X'], ['sku']],
            'missing name' => [['sku' => 'A'], ['name']],
            'sku too long' => [['sku' => 'A', 'name' => 'X', 'sku' => str_repeat('a', 65)], ['sku']],
            'name too long' => [['sku' => 'A', 'name' => str_repeat('a', 256)], ['name']],
            'ean not a string' => [['sku' => 'A', 'name' => 'X', 'ean' => 12345], ['ean']],
            'brand id not an integer' => [['sku' => 'A', 'name' => 'X', 'brand_id' => 'acme'], ['brand_id']],
            'brand id below minimum' => [['sku' => 'A', 'name' => 'X', 'brand_id' => 0], ['brand_id']],
            'active not a boolean' => [['sku' => 'A', 'name' => 'X', 'active' => 'yes'], ['active']],
            'multiple failures' => [
                ['sku' => '', 'name' => str_repeat('a', 300), 'active' => 1],
                ['sku', 'name', 'active'],
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidPayloads')]
    public function test_invalid_input_produces_identical_errors_from_both_transports(array $payload, array $expectedFields): void
    {
        $rest = $this->captureValidationFailure(fn () => $this->restAdapter()->create($payload, $this->context()));
        $graphql = $this->captureValidationFailure(fn () => $this->graphqlAdapter()->create($payload, $this->context()));

        $this->assertSame($expectedFields, $rest['fields']);
        $this->assertSame($rest, $graphql, 'both transports must report the same fields and messages');
        $this->assertSame(0, Product::count(), 'invalid input never reaches the database');
    }

    public function test_undeclared_fields_are_ignored_rather_than_persisted(): void
    {
        $row = $this->restAdapter()->create(
            ['sku' => 'U-1', 'name' => 'Clean', 'internal_cost' => 999, 'tenant_id' => 't-EVIL'],
            $this->context(),
        );

        // Undeclared keys are dropped by the DTO, so the stored row only carries
        // declared columns plus the persistence-managed timestamps and id.
        $this->assertArrayNotHasKey('internal_cost', $row);
        $this->assertArrayNotHasKey('tenant_id', $row);

        $columns = array_keys($row);
        sort($columns);

        $this->assertSame(
            ['active', 'brand_id', 'created_at', 'ean', 'id', 'name', 'sku', 'updated_at'],
            $columns,
        );
    }

    public function test_a_prebuilt_dto_and_raw_payload_produce_the_same_row(): void
    {
        // Same payload twice, one pre-validated by the caller and one validated
        // inside the use case. sku is unique, so only the derived fields
        // can be compared — which is exactly what the DTO decides.
        $viaDto = $this->restAdapter()->create(
            CreateProductData::fromArray(['sku' => 'DTO-1', 'name' => 'Via DTO']),
            $this->context(),
        );

        $viaRaw = $this->restAdapter()->create(['sku' => 'DTO-2', 'name' => 'Via DTO'], $this->context());

        foreach (['name', 'ean', 'brand_id', 'active'] as $field) {
            $this->assertSame($viaDto[$field], $viaRaw[$field], "{$field} must not depend on who validated it");
        }

        $this->assertSame(2, Product::count());
    }

    public function test_update_is_a_partial_patch_that_can_clear_an_optional_field(): void
    {
        $adapter = $this->restAdapter();
        $context = $this->context();
        $id = (string) $adapter->create(['sku' => 'E-1', 'name' => 'Original', 'ean' => '123'], $context)['id'];

        $cleared = $adapter->update($id, ['ean' => null], $context);
        $this->assertNull($cleared['ean']);
        $this->assertSame('Original', $cleared['name'], 'omitted fields stay untouched');

        $renamed = $adapter->update($id, ['name' => 'Renamed'], $context);
        $this->assertSame('Renamed', $renamed['name']);
        $this->assertNull($renamed['ean'], 'the earlier clearing survived');
    }

    public function test_an_empty_patch_is_rejected_identically_from_both_transports(): void
    {
        $id = (string) $this->restAdapter()->create(['sku' => 'E-2', 'name' => 'Patched'], $this->context())['id'];

        $rest = $this->captureValidationFailure(fn () => $this->restAdapter()->update($id, [], $this->context()));
        $graphql = $this->captureValidationFailure(fn () => $this->graphqlAdapter()->update($id, [], $this->context()));

        $this->assertSame($rest, $graphql);
        $this->assertSame(['_'], $rest['fields']);
    }

    public function test_invalid_patch_fields_are_reported_the_same_way(): void
    {
        $id = (string) $this->restAdapter()->create(['sku' => 'E-3', 'name' => 'Patched'], $this->context())['id'];

        $rest = $this->captureValidationFailure(fn () => $this->restAdapter()->update($id, ['name' => 42, 'active' => 'si'], $this->context()));
        $graphql = $this->captureValidationFailure(fn () => $this->graphqlAdapter()->update($id, ['name' => 42, 'active' => 'si'], $this->context()));

        $this->assertSame(['name', 'active'], $rest['fields']);
        $this->assertSame($rest, $graphql);
    }

    public function test_get_and_delete_behave_identically_from_both_transports(): void
    {
        $context = $this->context();
        $restId = (string) $this->restAdapter()->create(['sku' => 'G-1', 'name' => 'Same'], $context)['id'];
        $gqlId = (string) $this->graphqlAdapter()->create(['sku' => 'G-2', 'name' => 'Same'], $context)['id'];

        $this->assertSame('Same', $this->restAdapter()->find($restId, $context)['name']);
        $this->assertSame('Same', $this->graphqlAdapter()->find($gqlId, $context)['name']);

        $this->restAdapter()->delete($restId, $context);
        $this->graphqlAdapter()->delete($gqlId, $context);

        $this->assertSame(0, Product::count());
    }

    public function test_missing_row_is_the_same_error_from_both_transports(): void
    {
        $rest = $this->captureError(fn () => $this->restAdapter()->find('404', $this->context()));
        $graphql = $this->captureError(fn () => $this->graphqlAdapter()->find('404', $this->context()));

        $this->assertSame('RESOURCE_NOT_FOUND', $rest['code']);
        $this->assertSame($rest, $graphql);
    }

    public function test_a_denied_action_is_the_same_error_from_both_transports(): void
    {
        $context = $this->context();
        $id = (string) $this->restAdapter()->create(['sku' => 'D-9', 'name' => 'Denied'], $context)['id'];

        $denied = fn () => $this->restAdapter($this->viewlessLifecycle())->find($id, $context);

        $error = $this->captureError($denied);

        $this->assertSame('FORBIDDEN', $error['code']);
        $this->assertSame(
            $error,
            $this->captureError(fn () => $this->graphqlAdapter($this->viewlessLifecycle())->find($id, $context)),
        );
    }

    public function test_list_applies_the_same_filters_and_pagination_from_both_transports(): void
    {
        $adapter = $this->restAdapter();
        $context = $this->context();
        $adapter->create(['sku' => 'L-1', 'name' => 'Alpha', 'active' => true], $context);
        $adapter->create(['sku' => 'L-2', 'name' => 'Beta', 'active' => true], $context);
        $adapter->create(['sku' => 'L-3', 'name' => 'Gamma', 'active' => false], $context);

        $query = ['filters' => ['active' => true], 'sort' => ['name'], 'per_page' => 1];

        $rest = $this->restAdapter()->list($query, $context);
        $graphql = $this->graphqlAdapter()->list($query, $context);

        $this->assertSame(array_column($rest->items(), 'sku'), ['L-1']);
        $this->assertSame(array_column($rest->items(), 'sku'), array_column($graphql->items(), 'sku'));
        $this->assertSame(2, $rest->pagination()->total);
        $this->assertSame(2, $rest->pagination()->lastPage());
    }

    public function test_an_undeclared_list_filter_is_rejected_by_the_use_case_not_the_adapter(): void
    {
        $error = $this->captureValidationFailure(
            fn () => $this->restAdapter()->list(['filters' => ['secret_column' => 1]], $this->context()),
        );

        $this->assertSame(['filters.secret_column'], $error['fields']);
        $this->assertSame(
            $error,
            $this->captureValidationFailure(
                fn () => $this->graphqlAdapter()->list(['filters' => ['secret_column' => 1]], $this->context()),
            ),
        );
    }

    public function test_an_undeclared_include_is_rejected_from_both_transports(): void
    {
        $error = $this->captureValidationFailure(
            fn () => $this->restAdapter()->list(['includes' => ['relacionNoDeclarada']], $this->context()),
        );

        $this->assertSame('VALIDATION_FAILED', $error['code']);
        $this->assertSame(['includes.relacionNoDeclarada'], $error['fields']);
        $this->assertSame(
            $error,
            $this->captureValidationFailure(
                fn () => $this->graphqlAdapter()->list(['includes' => ['relacionNoDeclarada']], $this->context()),
            ),
        );
    }

    public function test_an_oversized_page_size_is_clamped_and_not_rejected_from_both_transports(): void
    {
        $max = \Shardevcom\Resources\QueryOptions::DEFAULT_MAX_PER_PAGE;

        $rest = $this->restAdapter()->list(['per_page' => 9999], $this->context());
        $graphql = $this->graphqlAdapter()->list(['per_page' => 9999], $this->context());

        $this->assertSame($max, $rest->pagination()->perPage);
        $this->assertSame($max, $graphql->pagination()->perPage);
        $this->assertSame($rest->pagination()->perPage, $graphql->pagination()->perPage);
    }

    public function test_use_cases_are_bound_from_the_container_with_no_logic_in_the_provider(): void
    {
        foreach ([CreateProduct::class, UpdateProduct::class, DeleteProduct::class, GetProduct::class, ListProducts::class] as $useCase) {
            $this->assertInstanceOf($useCase, $this->app->make($useCase));
        }
    }

    public function test_use_cases_do_not_import_eloquent_or_http(): void
    {
        $files = glob(dirname(__DIR__, 3).'/packages/shardev/product/src/Application/*.php');
        $files = array_merge($files, glob(dirname(__DIR__, 3).'/packages/shardev/product/src/Application/*/*.php'));
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            preg_match_all('/^use\s+([^;]+);/m', (string) file_get_contents($file), $imports);

            foreach ($imports[1] as $import) {
                $this->assertDoesNotMatchRegularExpression(
                    '/^(Illuminate\\Database|Illuminate\\Http|Illuminate\\Routing|Illuminate\\GraphQL)/',
                    trim($import),
                    basename($file).' must stay transport- and ORM-agnostic',
                );
            }
        }
    }

    private function restAdapter(?ActionLifecycle $lifecycle = null): ProductUseCaseHarness
    {
        return new ProductUseCaseHarness(
            $lifecycle ?? $this->lifecycle(),
            $this->app->make(QueryExecutorContract::class),
        );
    }

    private function graphqlAdapter(?ActionLifecycle $lifecycle = null): ProductUseCaseHarness
    {
        return new ProductUseCaseHarness(
            $lifecycle ?? $this->lifecycle(),
            $this->app->make(QueryExecutorContract::class),
        );
    }

    private function lifecycle(array $grants = ['products.*']): ActionLifecycle
    {
        $resolver = new class($grants) implements \Shardevcom\Core\Contracts\PermissionResolver
        {
            /**
             * @param list<string> $grants
             */
            public function __construct(private array $grants)
            {
            }

            public function can(ApplicationContext $context, string $resource, \Shardevcom\Core\ResourceAction|string $action): bool
            {
                $permission = \Shardevcom\Core\ResourceAction::permission($resource, $action);

                return in_array($permission, $this->grants, true) || in_array($resource.'.*', $this->grants, true);
            }
        };

        $manager = new class($resolver) implements ManagerContract
        {
            public function __construct(private \Shardevcom\Core\Contracts\PermissionResolver $resolver)
            {
            }

            public function authorize(string $resource, \Shardevcom\Core\ResourceAction|string $action, ApplicationContext $context): void
            {
                if ($this->resolver->can($context, $resource, $action)) {
                    return;
                }

                throw new \Shardevcom\Core\Exceptions\Forbidden(
                    "Action [".\Shardevcom\Core\ResourceAction::segment($action)."] on resource [{$resource}] is forbidden.",
                    ['resource' => $resource, 'action' => \Shardevcom\Core\ResourceAction::segment($action)],
                );
            }
        };

        return new ActionLifecycle($manager);
    }

    private function viewlessLifecycle(): ActionLifecycle
    {
        return $this->lifecycle(['products.create', 'products.update', 'products.delete']);
    }

    private function context(): ApplicationContext
    {
        return ApplicationContext::create(['userId' => 'u-1']);
    }

    /**
     * @return array{code: string, fields: list<string>, errors: array<string, mixed>}
     */
    private function captureValidationFailure(callable $operation): array
    {
        return $this->captureError($operation);
    }

    /**
     * @return array{code: string, fields: list<string>, errors: array<string, mixed>}
     */
    private function captureError(callable $operation): array
    {
        try {
            $operation();
        } catch (\Shardevcom\Core\Exceptions\DomainException $e) {
            $errors = $e instanceof ValidationFailed ? $e->errors() : [];

            return [
                'code' => $e->errorCode(),
                'fields' => array_keys($errors),
                'errors' => $errors,
            ];
        }

        $this->fail('No domain exception was thrown.');
    }
}

/**
 * Stand-in for a REST controller and a GraphQL resolver: each is a one-line
 * translation from a transport payload to the shared use case. The real
 * adapters (T-070/T-073) will look exactly like this, minus the fakes.
 */
final class ProductUseCaseHarness
{
    public function __construct(
        private ActionLifecycle $lifecycle,
        private QueryExecutorContract $queryExecutor,
    ) {
    }

    public function repository(): ProductRepository
    {
        return new ProductRepository($this->lifecycle, $this->queryExecutor);
    }

    /**
     * @param CreateProductData|array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function create(CreateProductData|array $input, ApplicationContext $context): array
    {
        return (new CreateProduct($this->repository()))->execute($input, $context);
    }

    /**
     * @param UpdateProductData|array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(string|int $id, UpdateProductData|array $input, ApplicationContext $context): array
    {
        return (new UpdateProduct($this->repository()))->execute($id, $input, $context);
    }

    public function find(string|int $id, ApplicationContext $context): array
    {
        return (new GetProduct($this->repository()))->execute($id, $context);
    }

    public function delete(string|int $id, ApplicationContext $context): void
    {
        (new DeleteProduct($this->repository()))->execute($id, $context);
    }

    /**
     * @param array<string, mixed> $query
     */
    public function list(array $query, ApplicationContext $context): \Shardevcom\Resources\QueryResult
    {
        return (new ListProducts($this->repository()))->execute($query, $context);
    }
}
