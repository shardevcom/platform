<?php

declare(strict_types=1);

namespace Tests\Feature\Product;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Shardevcom\Authorization\AuthorizationManager;
use Shardevcom\Authorization\InMemoryPermissionResolver;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\Contracts\AuthorizationManager as ManagerContract;
use Shardevcom\Core\Contracts\PermissionResolver;
use Shardevcom\Core\Contracts\QueryExecutor as QueryExecutorContract;
use Shardevcom\Core\Contracts\ResourceRegistry;
use Shardevcom\Core\Exceptions\Conflict;
use Shardevcom\Core\Exceptions\Forbidden;
use Shardevcom\Core\Exceptions\ResourceNotFound;
use Shardevcom\Core\Exceptions\ResourceOperationNotSupported;
use Shardevcom\Core\Exceptions\ValidationFailed;
use Shardevcom\Core\Lifecycle\ActionLifecycle;
use Shardevcom\Core\Repositories\AbstractResourceRepository;
use Shardevcom\Core\ResourceAction;
use Shardevcom\Product\Models\Brand;
use Shardevcom\Product\Models\Product;
use Shardevcom\Product\ProductResourceDefinition;
use Shardevcom\Product\Repositories\ProductRepository;
use Shardevcom\Resources\QueryOptions;
use Shardevcom\Resources\QueryResult;
use Shardevcom\Resources\ResourceDefinition;
use Tests\TestCase;

/**
 * T-053 acceptance: the demo product resource works end to end on sqlite
 * through ProductRepository — create → find → query(filtered) → update →
 * delete with authorization granted, and a denied view that stops before the
 * database is ever touched.
 */
final class ProductRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_resource_is_auto_registered_on_boot(): void
    {
        $registry = $this->app->make(ResourceRegistry::class);

        $this->assertTrue($registry->has('products'));
        $this->assertSame(ProductRepository::class, $registry->get('products')->repositoryClass());
        $this->assertSame(Product::class, $registry->get('products')->modelClass());
    }

    public function test_full_crud_round_trip(): void
    {
        $repo = $this->repository();
        $context = $this->context();

        $created = $repo->create(
            ['sku' => 'WID-001', 'ean' => '1234567890123', 'name' => 'Widget', 'active' => true],
            $context
        );

        $this->assertSame('WID-001', $created['sku']);
        $this->assertTrue($created['active']);

        $found = $repo->findOrFail((string) $created['id'], $context);
        $this->assertSame('Widget', $found['name']);

        $updated = $repo->update((string) $created['id'], ['name' => 'Widget II'], $context);
        $this->assertSame('Widget II', $updated['name']);
        $this->assertSame('WID-001', $updated['sku'], 'a patch only touches the given columns');

        $repo->delete((string) $created['id'], $context);
        $this->assertNull($repo->find((string) $created['id'], $context));
        $this->assertSame(0, Product::count(), 'products have no soft deletes');
    }

    public function test_query_with_filters_sorting_search_and_includes(): void
    {
        $repo = $this->repository();
        $context = $this->context();
        $acme = Brand::factory()->create(['name' => 'Acme']);
        Brand::factory()->create(['name' => 'Other']);

        $repo->create(['sku' => 'WID-001', 'name' => 'Widget', 'brand_id' => $acme->id, 'active' => true], $context);
        $repo->create(['sku' => 'GAD-002', 'name' => 'Gadget', 'active' => true], $context);
        $repo->create(['sku' => 'OLD-003', 'name' => 'Obsolete', 'active' => false], $context);

        $definition = $repo->resourceDefinition();

        $active = $repo->query(
            QueryOptions::create($definition, ['filters' => ['active' => true], 'sort' => ['name']]),
            $context
        );
        $this->assertSame(['Gadget', 'Widget'], array_column($active->items(), 'name'));
        $this->assertSame(2, $active->pagination()->total);

        $byBrand = $repo->query(
            QueryOptions::create($definition, ['filters' => ['brand_id' => [$acme->id]]]),
            $context
        );
        $this->assertSame(['Widget'], array_column($byBrand->items(), 'name'));

        $search = $repo->query(QueryOptions::create($definition, ['search' => 'wid']), $context);
        $this->assertSame(['WID-001'], array_column($search->items(), 'sku'));

        $withBrand = $repo->query(
            QueryOptions::create($definition, ['includes' => ['brand'], 'sort' => ['name']]),
            $context
        );

        $brandsByName = [];

        foreach ($withBrand->items() as $item) {
            $brandsByName[$item['name']] = $item['brand']['name'] ?? null;
        }

        $this->assertSame(
            ['Gadget' => null, 'Obsolete' => null, 'Widget' => 'Acme'],
            $brandsByName,
        );

        $paged = $repo->query(QueryOptions::create($definition, ['per_page' => 2]), $context);
        $this->assertSame(2, $paged->pagination()->lastPage());
        $this->assertSame(3, $paged->pagination()->total);
    }

    public function test_find_many_returns_rows_in_request_order(): void
    {
        $repo = $this->repository();
        $context = $this->context();
        $repo->create(['sku' => 'A', 'name' => 'A'], $context);
        $repo->create(['sku' => 'B', 'name' => 'B'], $context);

        $this->assertSame(
            ['B', 'A'],
            array_column($repo->findMany(['2', '999', '1'], $context), 'sku'),
        );
    }

    public function test_a_denied_view_stops_before_the_database(): void
    {
        // A query executor that fails the test if it is ever reached.
        $explodingExecutor = new class implements QueryExecutorContract
        {
            public function execute(QueryOptions $options, ApplicationContext $context): QueryResult
            {
                throw new \LogicException('The query executor must not be reached.');
            }
        };

        $repo = new ProductRepository(
            $this->lifecycle(['products.create']), // no products.view
            $explodingExecutor,
        );

        Product::factory()->create(['sku' => 'SECRET']);

        try {
            $repo->find('1', $this->context());
            $this->fail('Forbidden was not thrown.');
        } catch (Forbidden $e) {
            $this->assertSame('FORBIDDEN', $e->errorCode());
            $this->assertSame('products', $e->context()['resource']);
            $this->assertSame('view', $e->context()['action']);
        }
    }

    /**
     * T-100 acceptance: a denied write must not reach persistence at all.
     * The exploding query executor only proves the read path stops; a write
     * could still land after authorization failed, so this one watches the
     * SQL that actually reaches the connection.
     */
    public function test_a_denied_write_never_reaches_the_database(): void
    {
        // view + create only: the update and delete grants are what this test removes.
        $repo = $this->repository(['products.view', 'products.create']);
        $id = (string) $repo->create(['sku' => 'W-1', 'name' => 'Writable'], $this->context())['id'];

        $statements = [];
        \Illuminate\Support\Facades\DB::listen(function ($query) use (&$statements) {
            $statements[] = strtolower(trim((string) $query->sql));
        });

        try {
            $repo->update($id, ['name' => 'Hacked'], $this->context());
            $this->fail('Forbidden was not thrown.');
        } catch (Forbidden $e) {
            $this->assertSame('FORBIDDEN', $e->errorCode());
            $this->assertSame('update', $e->context()['action']);
        }

        try {
            $repo->delete($id, $this->context());
            $this->fail('Forbidden was not thrown.');
        } catch (Forbidden $e) {
            $this->assertSame('FORBIDDEN', $e->errorCode());
            $this->assertSame('delete', $e->context()['action']);
        }

        foreach (['update', 'delete'] as $forbidden) {
            $this->assertSame(
                [],
                array_values(array_filter($statements, fn (string $sql) => str_starts_with($sql, $forbidden))),
                "a denied {$forbidden} must never reach the database",
            );
        }

        $this->assertSame(1, Product::where('id', $id)->count(), 'the row must still exist');
        $this->assertSame('Writable', Product::where('id', $id)->value('name'), 'the row must be untouched');
    }

    public function test_authorization_uses_the_declared_actions(): void
    {
        $definition = ProductResourceDefinition::definition();

        foreach ([ResourceAction::VIEW, ResourceAction::CREATE, ResourceAction::UPDATE, ResourceAction::DELETE] as $action) {
            $this->assertTrue($definition->supportsAction($action));
        }

        $this->assertFalse($definition->supportsAction(ResourceAction::RESTORE));
    }

    public function test_restore_is_refused_because_the_resource_does_not_declare_it(): void
    {
        $repo = $this->repository();

        $this->assertFalse($repo->supportsOperation(AbstractResourceRepository::RESTORE));

        $this->expectException(ResourceOperationNotSupported::class);
        $repo->restore('1', $this->context());
    }

    public function test_declared_columns_only_can_be_written(): void
    {
        $repo = $this->repository();

        try {
            $repo->create(['sku' => 'X', 'name' => 'X', 'internal_cost' => 99], $this->context());
            $this->fail('ValidationFailed was not thrown.');
        } catch (ValidationFailed $e) {
            $this->assertArrayHasKey('internal_cost', $e->errors());
        }

        $this->assertSame(0, Product::count(), 'nothing may be written');
    }

    public function test_a_duplicate_sku_is_reported_as_a_conflict(): void
    {
        $repo = $this->repository();
        $context = $this->context();
        $repo->create(['sku' => 'DUP', 'name' => 'First'], $context);

        try {
            $repo->create(['sku' => 'DUP', 'name' => 'Second'], $context);
            $this->fail('Conflict was not thrown.');
        } catch (Conflict $e) {
            // A driver exception would leak SQL and vendor internals.
            $this->assertSame('CONFLICT', $e->errorCode());
            $this->assertSame('products', $e->context()['resource']);
            $this->assertInstanceOf(\Illuminate\Database\QueryException::class, $e->getPrevious());
            $this->assertStringNotContainsString('insert into', $e->getMessage());
        }

        $this->assertSame(1, Product::count());
    }

    public function test_update_and_delete_of_a_missing_row_report_not_found(): void
    {
        $repo = $this->repository();
        $context = $this->context();

        try {
            $repo->update('999', ['name' => 'Ghost'], $context);
            $this->fail('ResourceNotFound was not thrown.');
        } catch (ResourceNotFound) {
            // expected
        }

        $this->expectException(ResourceNotFound::class);
        $repo->delete('999', $context);
    }

    public function test_the_factory_builds_queryable_rows(): void
    {
        $product = Product::factory()->create();

        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertNotNull($product->brand_id);

        $orphan = Product::factory()->withoutBrand()->create();
        $this->assertNull($orphan->fresh()->brand_id);
    }

    public function test_the_repository_satisfies_its_boundary_contracts(): void
    {
        $repo = $this->repository();

        $this->assertInstanceOf(\Shardevcom\Core\Contracts\ResourceRepository::class, $repo);
        $this->assertSame('products', $repo->resourceName());
        $this->assertSame([
            'create', 'find', 'findOrFail', 'findMany', 'query', 'list', 'update', 'delete', 'import', 'export',
        ], $repo->supportedOperations());
    }

    public function test_the_definition_is_the_single_source_of_truth(): void
    {
        $definition = ProductResourceDefinition::definition();

        $this->assertSame($definition->name(), $this->repository()->resourceName());
        $this->assertSame(ProductRepository::class, $definition->repositoryClass());
        $this->assertSame(Product::class, $definition->modelClass());
        $this->assertSame(['sku', 'ean', 'brand_id', 'active'], $definition->filters());
        $this->assertSame(['brand'], $definition->includes());
    }

    /**
     * @param list<string> $grants
     */
    private function repository(array $grants = ['products.*']): ProductRepository
    {
        return new ProductRepository(
            $this->lifecycle($grants),
            $this->app->make(QueryExecutorContract::class),
        );
    }

    /**
     * @param list<string> $grants
     */
    private function lifecycle(array $grants = ['products.*']): ActionLifecycle
    {
        return new ActionLifecycle(new AuthorizationManager(new InMemoryPermissionResolver($grants)));
    }

    private function context(): ApplicationContext
    {
        return ApplicationContext::create(['userId' => 'u-1']);
    }
}
