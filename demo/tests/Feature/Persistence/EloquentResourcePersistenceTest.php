<?php

declare(strict_types=1);

namespace Tests\Feature\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Shardevcom\Authorization\AuthorizationManager;
use Shardevcom\Authorization\InMemoryPermissionResolver;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\Contracts\ResourceRepository as ResourceRepositoryContract;
use Shardevcom\Core\Exceptions\Conflict;
use Shardevcom\Core\Exceptions\Forbidden;
use Shardevcom\Core\Exceptions\ResourceNotFound;
use Shardevcom\Core\Exceptions\ResourceOperationNotSupported;
use Shardevcom\Core\Exceptions\ValidationFailed;
use Shardevcom\Core\Lifecycle\ActionLifecycle;
use Shardevcom\Core\Repositories\AbstractResourceRepository;
use Shardevcom\Core\ResourceAction;
use Shardevcom\Persistence\EloquentQueryExecutor;
use Shardevcom\Persistence\EloquentResourcePersistence;
use Shardevcom\Resources\QueryOptions;
use Shardevcom\Resources\ResourceDefinition;
use Tests\TestCase;

/**
 * T-052 acceptance: the abstract repository drives real sqlite through the
 * Eloquent persistence adapter — mass assignment, scoped reads/writes, and
 * no implicit transaction anywhere.
 */
class PersistedWidget extends Model
{
    use SoftDeletes;

    protected $table = 'persisted_widgets';

    protected $fillable = ['sku', 'name', 'active', 'tenant_id'];

    protected $casts = ['active' => 'bool'];
}

/**
 * Same table, but the mass-assignment allow-list intentionally omits `secret`
 * so the $fillable gate is observable.
 */
class GuardedWidget extends PersistedWidget
{
    protected $table = 'persisted_widgets';

    protected $fillable = ['sku', 'name', 'active', 'tenant_id'];
}

class WidgetRepository extends AbstractResourceRepository
{
    use EloquentResourcePersistence;

    protected array $supportedOperations = [
        AbstractResourceRepository::CREATE,
        AbstractResourceRepository::FIND,
        AbstractResourceRepository::FIND_OR_FAIL,
        AbstractResourceRepository::FIND_MANY,
        AbstractResourceRepository::QUERY,
        AbstractResourceRepository::UPDATE,
        AbstractResourceRepository::DELETE,
        AbstractResourceRepository::RESTORE,
    ];

    public function resourceName(): string
    {
        return 'widgets';
    }

    public function resourceDefinition(): ResourceDefinition
    {
        return ResourceDefinition::make('widgets')
            ->withActions([ResourceAction::VIEW, ResourceAction::CREATE, ResourceAction::UPDATE, ResourceAction::DELETE, ResourceAction::RESTORE])
            ->withFields(['id', 'sku', 'name', 'active', 'tenant_id'])
            ->withFilters(['active'])
            ->withSorting(['name'])
            ->withTenantScope()
            ->withModel(PersistedWidget::class);
    }
}

/**
 * Definition declaring a column the model does not allow for mass assignment.
 */
final class GuardedWidgetRepository extends WidgetRepository
{
    public function resourceDefinition(): ResourceDefinition
    {
        return ResourceDefinition::make('widgets')
            ->withFields(['id', 'sku', 'name', 'secret'])
            ->withTenantScope()
            ->withModel(GuardedWidget::class);
    }
}

/**
 * A repository that supports everything but restore, to prove the trait's
 * restore primitive is unreachable when undeclared.
 */
final class WidgetRepositoryWithoutRestore extends WidgetRepository
{
    protected array $supportedOperations = [
        AbstractResourceRepository::CREATE,
        AbstractResourceRepository::FIND,
        AbstractResourceRepository::FIND_OR_FAIL,
        AbstractResourceRepository::FIND_MANY,
        AbstractResourceRepository::QUERY,
        AbstractResourceRepository::UPDATE,
        AbstractResourceRepository::DELETE,
    ];
}

final class EloquentResourcePersistenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('persisted_widgets');

        Schema::create('persisted_widgets', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('tenant_id')->nullable();
            $table->string('sku');
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->string('secret')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('persisted_widgets');

        parent::tearDown();
    }

    public function test_create_read_update_and_delete_round_trip_on_sqlite(): void
    {
        $repo = $this->repository();
        $context = $this->context();

        $created = $repo->create(['sku' => 'W-1', 'name' => 'Widget', 'active' => true], $context);
        $this->assertSame('W-1', $created['sku']);
        $this->assertSame('t-A', $created['tenant_id']);

        $found = $repo->findOrFail((string) $created['id'], $context);
        $this->assertSame('Widget', $found['name']);

        $updated = $repo->update((string) $created['id'], ['name' => 'Widget II'], $context);
        $this->assertSame('Widget II', $updated['name']);
        $this->assertSame('W-1', $updated['sku'], 'a patch only touches the given columns');

        $repo->delete((string) $created['id'], $context);
        $this->assertNull($repo->find((string) $created['id'], $context));
        $this->assertSame(0, PersistedWidget::count(), 'gone for reads');
        $this->assertSame(1, PersistedWidget::withTrashed()->count(), 'archived, not destroyed');
    }

    public function test_find_many_returns_visible_rows_in_request_order(): void
    {
        $repo = $this->repository();
        $context = $this->context();
        $repo->create(['sku' => 'A', 'name' => 'A'], $context);
        $repo->create(['sku' => 'B', 'name' => 'B'], $context);
        $repo->create(['sku' => 'C', 'name' => 'C'], $context);

        $rows = $repo->findMany(['3', '1', '999'], $context);

        $this->assertSame(['C', 'A'], array_column($rows, 'sku'));
    }

    public function test_a_row_from_another_tenant_is_invisible(): void
    {
        $repo = $this->repository();
        $created = $repo->create(['sku' => 'X', 'name' => 'X'], $this->context());
        $id = (string) $created['id'];

        $this->assertNull($repo->find($id, $this->context(['tenantId' => 't-B'])));
        $this->assertSame([], $repo->findMany([$id], $this->context(['tenantId' => 't-B'])));
        $this->assertSame([], $repo->query(
            QueryOptions::create($repo->resourceDefinition()),
            $this->context(['tenantId' => 't-B']),
        )->items());
    }

    public function test_update_and_delete_of_an_invisible_row_report_not_found(): void
    {
        $repo = $this->repository();
        $created = $repo->create(['sku' => 'X', 'name' => 'X'], $this->context());
        $id = (string) $created['id'];
        $otherTenant = $this->context(['tenantId' => 't-B']);

        try {
            $repo->update($id, ['name' => 'hijack'], $otherTenant);
            $this->fail('ResourceNotFound was not thrown.');
        } catch (ResourceNotFound $e) {
            $this->assertSame('RESOURCE_NOT_FOUND', $e->errorCode());
        }

        try {
            $repo->delete($id, $otherTenant);
            $this->fail('ResourceNotFound was not thrown.');
        } catch (ResourceNotFound) {
            $this->assertSame('X', PersistedWidget::find($id)->name, 'the row must be untouched');
        }
    }

    public function test_soft_deleted_rows_can_be_restored(): void
    {
        $repo = $this->repository();
        $context = $this->context();
        $created = $repo->create(['sku' => 'R', 'name' => 'Restorable'], $context);
        $id = (string) $created['id'];

        $repo->delete($id, $context);
        $this->assertNotNull(PersistedWidget::withTrashed()->find($id));

        $restored = $repo->restore($id, $context);
        $this->assertSame('Restorable', $restored['name']);
        $this->assertNull(PersistedWidget::withTrashed()->find($id)->deleted_at);
    }

    public function test_restore_is_refused_when_the_repository_does_not_declare_it(): void
    {
        $repo = new WidgetRepositoryWithoutRestore($this->lifecycle(), new EloquentQueryExecutor());

        $this->expectException(ResourceOperationNotSupported::class);
        $repo->restore('1', $this->context());
    }

    public function test_restore_of_a_row_deleted_for_good_reports_not_found(): void
    {
        $repo = new class($this->lifecycle(), new EloquentQueryExecutor()) extends WidgetRepository
        {
            public function resourceDefinition(): ResourceDefinition
            {
                return parent::resourceDefinition()->withModel(NonSoftDeletingWidget::class);
            }
        };

        $context = $this->context();
        $created = $repo->create(['sku' => 'H', 'name' => 'Hard delete'], $context);
        $id = (string) $created['id'];
        $repo->delete($id, $context);

        $this->assertSame(0, PersistedWidget::withTrashed()->count());

        $this->expectException(ResourceNotFound::class);
        $repo->restore($id, $context);
    }

    public function test_mass_assignment_goes_through_fillable(): void
    {
        $repo = new GuardedWidgetRepository($this->lifecycle(), new EloquentQueryExecutor());

        // `secret` is fillable nowhere on the model, so fill() drops it.
        $created = $repo->create(['sku' => 'S', 'name' => 'Secretive', 'secret' => 'leaked'], $this->context());

        $this->assertArrayNotHasKey('secret', $created);
        $this->assertNull(DB::table('persisted_widgets')->where('id', $created['id'])->value('secret'));
    }

    public function test_undeclared_columns_are_rejected_before_the_write(): void
    {
        $repo = $this->repository();

        try {
            $repo->create(['sku' => 'S', 'name' => 'Ok', 'internal_note' => 'x'], $this->context());
            $this->fail('ValidationFailed was not thrown.');
        } catch (ValidationFailed $e) {
            $this->assertSame(['internal_note'], array_keys($e->errors()));
        }

        $this->assertSame(0, PersistedWidget::count(), 'nothing may be written');
    }

    public function test_a_payload_cannot_choose_its_own_tenant(): void
    {
        $repo = $this->repository();

        $created = $repo->create(
            ['sku' => 'T', 'name' => 'T', 'tenant_id' => 't-EVIL'],
            $this->context(['tenantId' => 't-A']),
        );

        $this->assertSame('t-A', $created['tenant_id']);
        $this->assertSame('t-A', DB::table('persisted_widgets')->where('id', $created['id'])->value('tenant_id'));
    }

    public function test_writing_a_tenant_scoped_resource_without_a_tenant_is_refused(): void
    {
        $repo = $this->repository();

        $this->expectException(Conflict::class);
        $repo->create(['sku' => 'N', 'name' => 'N'], ApplicationContext::create());
    }

    public function test_a_denied_action_never_reaches_the_database(): void
    {
        $repo = $this->repository([]);
        $context = $this->context();

        try {
            $repo->create(['sku' => 'D', 'name' => 'D'], $context);
            $this->fail('Forbidden was not thrown.');
        } catch (Forbidden) {
            $this->assertSame(0, PersistedWidget::count(), 'no insert may happen');
        }
    }

    public function test_query_delegates_to_the_executor_and_returns_paginated_rows(): void
    {
        $repo = $this->repository();
        $context = $this->context();
        $repo->create(['sku' => 'Q1', 'name' => 'One', 'active' => true], $context);
        $repo->create(['sku' => 'Q2', 'name' => 'Two', 'active' => false], $context);

        $result = $repo->query(
            QueryOptions::create($repo->resourceDefinition(), [
                'filters' => ['active' => false],
                'sort' => ['name'],
            ]),
            $context,
        );

        $this->assertSame(['Two'], array_column($result->items(), 'name'));
        $this->assertSame(1, $result->pagination()->total);
    }

    public function test_a_missing_model_is_a_server_misconfiguration(): void
    {
        $repo = new class($this->lifecycle(), new EloquentQueryExecutor()) extends WidgetRepository
        {
            public function resourceDefinition(): ResourceDefinition
            {
                return ResourceDefinition::make('widgets');
            }
        };

        $this->expectException(\RuntimeException::class);
        $repo->create(['sku' => 'X', 'name' => 'X'], $this->context());
    }

    public function test_the_repository_keeps_its_boundary_contract(): void
    {
        $this->assertInstanceOf(ResourceRepositoryContract::class, $this->repository());
    }

    public function test_the_adapter_opens_no_transaction_of_its_own(): void
    {
        $adapter = (string) file_get_contents(
            dirname(__DIR__, 3).'/packages/shardev/persistence/src/EloquentResourcePersistence.php'
        );

        // Strip docblocks: the WHEN-to-transact guidance belongs there.
        $code = (string) preg_replace('#/\*\*.*?\*/#s', '', $adapter);

        $this->assertStringNotContainsString('DB::transaction', $code);
        $this->assertStringNotContainsString('beginTransaction', $code);
        $this->assertStringNotContainsString('commit(', $code);
        $this->assertStringNotContainsString('rollBack(', $code);
        $this->assertStringContainsString(
            'DB::transaction',
            $adapter,
            'the docblock must show a Use Case opening the transaction',
        );
    }

    /**
     * @param list<string> $grants
     */
    private function repository(array $grants = ['widgets.*']): WidgetRepository
    {
        return new WidgetRepository($this->lifecycle($grants), new EloquentQueryExecutor());
    }

    /**
     * @param list<string> $grants
     */
    private function lifecycle(array $grants = ['widgets.*']): ActionLifecycle
    {
        return new ActionLifecycle(new AuthorizationManager(new InMemoryPermissionResolver($grants)));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function context(array $overrides = []): ApplicationContext
    {
        return ApplicationContext::create(array_merge(['userId' => 'u-1', 'tenantId' => 't-A'], $overrides));
    }
}

class NonSoftDeletingWidget extends Model
{
    protected $table = 'persisted_widgets';

    protected $fillable = ['sku', 'name', 'active', 'tenant_id'];

    protected $casts = ['active' => 'bool'];
}
