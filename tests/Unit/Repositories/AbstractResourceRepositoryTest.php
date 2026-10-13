<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use Shardevcom\Authorization\AuthorizationManager;
use Shardevcom\Authorization\InMemoryPermissionResolver;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\Contracts\AuthorizationManager as ManagerContract;
use Shardevcom\Core\Contracts\QueryExecutor;
use Shardevcom\Core\Contracts\ResourceRepository as ResourceRepositoryContract;
use Shardevcom\Core\Exceptions\Conflict;
use Shardevcom\Core\Exceptions\Forbidden;
use Shardevcom\Core\Exceptions\ResourceNotFound;
use Shardevcom\Core\Exceptions\ResourceOperationNotSupported;
use Shardevcom\Core\Lifecycle\ActionLifecycle;
use Shardevcom\Core\Lifecycle\ActionInvocation;
use Shardevcom\Core\Lifecycle\LifecycleHooks;
use Shardevcom\Core\Repositories\AbstractResourceRepository;
use Shardevcom\Core\ResourceAction;
use Shardevcom\Resources\QueryOptions;
use Shardevcom\Resources\QueryResult;
use Shardevcom\Resources\ResourceDefinition;
use PHPUnit\Framework\TestCase;

/**
 * T-051 acceptance: the seven/eight base operations run through the
 * lifecycle, unsupported operations are refused, and nothing here needs a
 * database (fake persistence primitives + fake query executor).
 */
final class AbstractResourceRepositoryTest extends TestCase
{
    public function test_crud_through_fake_persistence_without_any_database(): void
    {
        $repo = $this->repository(['products.*']);
        $context = ApplicationContext::create(['userId' => 'u-1', 'tenantId' => 't-1']);

        $created = $repo->create(['sku' => 'ABC-1', 'name' => 'Anvil'], $context);
        $this->assertSame('ABC-1', $created['sku']);
        $this->assertSame('Anvil', $created['name']);
        $this->assertSame('1', (string) $created['id']);

        $this->assertSame($created, $repo->find('1', $context));
        $this->assertSame($created, $repo->findOrFail(1, $context));
        $this->assertSame([$created], $repo->findMany(['1'], $context));

        $updated = $repo->update('1', ['name' => 'Anvil II'], $context);
        $this->assertSame('Anvil II', $updated['name']);
        $this->assertSame('ABC-1', $updated['sku'], 'update merges the patch');

        $repo->delete('1', $context);
        $this->assertNull($repo->find('1', $context), 'a deleted row is no longer readable');
    }

    public function test_find_returns_null_and_findOrFail_throws_for_unknown_ids(): void
    {
        $repo = $this->repository(['products.*']);
        $context = ApplicationContext::create();

        $this->assertNull($repo->find('404', $context));

        $this->expectException(ResourceNotFound::class);
        $repo->findOrFail('404', $context);
    }

    public function test_find_many_skips_missing_ids_and_keeps_request_order(): void
    {
        $repo = $this->repository(['products.*']);
        $context = ApplicationContext::create();
        $repo->create(['sku' => 'A'], $context);
        $repo->create(['sku' => 'B'], $context);

        $this->assertSame(
            ['B', 'A'],
            array_column($repo->findMany(['2', '999', '1'], $context), 'sku'),
        );
    }

    public function test_find_many_with_no_ids_does_not_reach_persistence(): void
    {
        $repo = $this->repository(['products.*']);

        $this->assertSame([], $repo->findMany([], ApplicationContext::create()));
    }

    public function test_restore_on_a_subclass_that_does_not_support_it_is_refused(): void
    {
        $repo = $this->repository(['products.*']);

        $this->assertFalse($repo->supportsOperation(AbstractResourceRepository::RESTORE));
        $this->assertNotContains(AbstractResourceRepository::RESTORE, $repo->supportedOperations());

        try {
            $repo->restore('1', ApplicationContext::create());
            $this->fail('ResourceOperationNotSupported was not thrown.');
        } catch (ResourceOperationNotSupported $e) {
            $this->assertSame('RESOURCE_OPERATION_NOT_SUPPORTED', $e->errorCode());
            $this->assertSame('products', $e->context()['resource']);
            $this->assertSame('restore', $e->context()['operation']);
        }
    }

    public function test_unsupported_operations_are_refused_before_authorization_or_persistence(): void
    {
        $trace = new \ArrayObject();
        $repo = $this->repository(['products.restore'], $trace, ['products.*']);

        try {
            $repo->restore('1', ApplicationContext::create());
        } catch (ResourceOperationNotSupported) {
            // expected
        }

        $this->assertSame([], $repo->calls(), 'no persistence primitive may run');
        $this->assertSame([], $trace->getArrayCopy(), 'no lifecycle step may run for an unsupported operation');
    }

    public function test_supported_operations_can_include_restore(): void
    {
        $repo = new RestorableFakeProductRepository(
            $this->lifecycle(['products.*']),
            new FakeQueryExecutor(),
        );

        $context = ApplicationContext::create();
        $repo->create(['sku' => 'A'], $context);
        $repo->delete('1', $context);

        $this->assertContains(AbstractResourceRepository::RESTORE, $repo->supportedOperations());
        $this->assertSame('A', $repo->restore('1', $context)['sku']);
    }

    public function test_denied_action_never_reaches_persistence(): void
    {
        $trace = new \ArrayObject();
        $repo = $this->repository([], $trace);

        try {
            $repo->find('1', ApplicationContext::create(['userId' => 'u-1']));
            $this->fail('Forbidden was not thrown.');
        } catch (Forbidden $e) {
            $this->assertSame('FORBIDDEN', $e->errorCode());
        }

        $this->assertSame([], $repo->calls(), 'a denied operation must not touch persistence');
        $this->assertSame(
            ['authorize', 'failure:Action [view] on resource [products] is forbidden.'],
            array_values(array_filter(
                $trace->getArrayCopy(),
                static fn (string $entry): bool => str_starts_with($entry, 'authorize') || str_starts_with($entry, 'failure:'),
            )),
        );
    }

    public function test_every_operation_routes_through_the_lifecycle_in_order(): void
    {
        $trace = new \ArrayObject();
        $repo = new RestorableFakeProductRepository(
            $this->lifecycle(['products.*'], $trace),
            new FakeQueryExecutor(),
        );

        $context = ApplicationContext::create();
        $repo->create(['sku' => 'A'], $context);
        $repo->find('1', $context);
        $repo->findOrFail('1', $context);
        $repo->findMany(['1'], $context);
        $repo->query($this->queryOptions(), $context);
        $repo->update('1', ['sku' => 'B'], $context);
        $repo->delete('1', $context);
        $repo->restore('1', $context);

        $actions = array_values(array_filter(
            $trace->getArrayCopy(),
            static fn (string $entry): bool => str_starts_with($entry, 'before:'),
        ));

        $this->assertSame([
            'before:products.create',
            'before:products.view',
            'before:products.view',
            'before:products.view',
            'before:products.view',
            'before:products.update',
            'before:products.delete',
            'before:products.restore',
        ], $actions, 'every operation crosses the lifecycle with its own action');
    }

    public function test_query_delegates_the_validated_options_to_the_executor(): void
    {
        $executor = new FakeQueryExecutor();
        $repo = new FakeProductRepository($this->lifecycle(['products.view']), $executor);
        $options = $this->queryOptions();

        $result = $repo->query($options, ApplicationContext::create());

        $this->assertSame([$options], $executor->received);
        $this->assertSame([['id' => 1]], $result->items());
    }

    public function test_delete_of_a_missing_row_raises_not_found(): void
    {
        $repo = $this->repository(['products.*']);

        $this->expectException(ResourceNotFound::class);
        $repo->delete('999', ApplicationContext::create());
    }

    public function test_update_of_a_missing_row_raises_not_found(): void
    {
        $repo = $this->repository(['products.*']);

        $this->expectException(ResourceNotFound::class);
        $repo->update('999', ['sku' => 'X'], ApplicationContext::create());
    }

    public function test_a_create_that_cannot_be_read_back_is_a_conflict(): void
    {
        $repo = new class($this->lifecycle(['products.create']), new FakeQueryExecutor()) extends AbstractResourceRepository
        {
            protected function persistNew(array $attributes, ApplicationContext $context): ?array
            {
                return null;
            }

            protected function persistFind(string $id, ApplicationContext $context, array $includes = []): ?array
            {
                return null;
            }

            protected function persistFindMany(array $ids, ApplicationContext $context): array
            {
                return [];
            }

            protected function persistUpdate(string $id, array $attributes, ApplicationContext $context): ?array
            {
                return null;
            }

            protected function persistDelete(string $id, ApplicationContext $context): bool
            {
                return false;
            }

            protected function persistRestore(string $id, ApplicationContext $context): ?array
            {
                return null;
            }

            public function resourceName(): string
            {
                return 'products';
            }
        };

        $this->expectException(Conflict::class);
        $repo->create(['sku' => 'A'], ApplicationContext::create());
    }

    public function test_repository_satisfies_the_boundary_contract(): void
    {
        $this->assertInstanceOf(ResourceRepositoryContract::class, $this->repository(['products.*']));
    }

    public function test_repository_base_has_no_eloquent_or_factory_code(): void
    {
        $files = glob(dirname(__DIR__, 3).'/packages/shardevcom/core/src/Repositories/*.php');
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);

            $this->assertStringNotContainsString('Eloquent', $source, basename($file).' must not name a persistence ORM');
            $this->assertStringNotContainsString('HasFactory', $source, basename($file).' must not depend on a test factory');
            $this->assertStringNotContainsString('DB::', $source, basename($file).' must not open transactions implicitly');
            $this->assertStringNotContainsString('Model', $source, basename($file).' must not reach for a persistence model');
        }
    }

    /**
     * @param list<string> $grants
     */
    private function repository(array $grants, ?\ArrayObject $trace = null): FakeProductRepository
    {
        return new FakeProductRepository($this->lifecycle($grants, $trace), new FakeQueryExecutor());
    }

    /**
     * @param list<string> $grants
     */
    private function lifecycle(array $grants, ?\ArrayObject $trace = null): ActionLifecycle
    {
        $inner = new AuthorizationManager(new InMemoryPermissionResolver($grants));

        $manager = new class($inner, $trace) implements ManagerContract
        {
            public function __construct(private ManagerContract $inner, private ?\ArrayObject $trace)
            {
            }

            public function authorize(string $resource, ResourceAction|string $action, ApplicationContext $context): void
            {
                $this->trace?->append('authorize');

                $this->inner->authorize($resource, $action, $context);
            }
        };

        $hooks = new class($trace) implements LifecycleHooks
        {
            public function __construct(private ?\ArrayObject $trace)
            {
            }

            public function beforeAction(ActionInvocation $invocation): void
            {
                $this->trace?->append('before:'.$invocation->permission());
            }

            public function afterAction(ActionInvocation $invocation, mixed $result): void
            {
                $this->trace?->append('after:'.$invocation->permission());
            }

            public function onActionFailure(ActionInvocation $invocation, \Throwable $failure): void
            {
                $this->trace?->append('failure:'.$failure->getMessage());
            }
        };

        return new ActionLifecycle($manager, $hooks);
    }

    private function queryOptions(): QueryOptions
    {
        return QueryOptions::create(
            ResourceDefinition::make('products')
                ->withFilters(['sku'])
                ->withFields(['id', 'sku'])
                ->withSorting(['id'])
                ->withModel(FakeProductRow::class),
            ['filters' => ['sku' => 'A']],
        );
    }
}

/**
 * In-memory persistence primitives: the whole point of T-051 is that the
 * access layer can be exercised with zero database involvement.
 */
class FakeProductRepository extends AbstractResourceRepository
{
    /** @var array<string, array<string, mixed>> */
    protected array $rows = [];

    /** @var array<string, array<string, mixed>> */
    protected array $deleted = [];

    protected int $sequence = 0;

    /** @var list<string> */
    private array $calls = [];

    /**
     * Persistence primitives actually reached — the evidence that a denied
     * or unsupported operation stopped at the gate.
     *
     * @return list<string>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    public function resourceName(): string
    {
        return 'products';
    }

    public function resourceDefinition(): ResourceDefinition
    {
        return ResourceDefinition::make('products')
            ->withFilters(['sku'])
            ->withFields(['id', 'sku'])
            ->withSorting(['id'])
            ->withIncludes([])
            ->withModel(FakeProductRow::class);
    }

    protected function persistNew(array $attributes, ApplicationContext $context): ?array
    {
        $this->calls[] = 'persistNew';

        $id = (string) ++$this->sequence;
        $this->rows[$id] = ['id' => $id] + $attributes;

        return $this->rows[$id];
    }

    protected function persistFind(string $id, ApplicationContext $context, array $includes = []): ?array
    {
        $this->calls[] = 'persistFind';

        return $this->rows[$id] ?? null;
    }

    protected function persistFindMany(array $ids, ApplicationContext $context): array
    {
        $this->calls[] = 'persistFindMany';
        $found = [];

        foreach ($ids as $id) {
            if (isset($this->rows[$id])) {
                $found[] = $this->rows[$id];
            }
        }

        return $found;
    }

    protected function persistUpdate(string $id, array $attributes, ApplicationContext $context): ?array
    {
        $this->calls[] = 'persistUpdate';

        if (! isset($this->rows[$id])) {
            return null;
        }

        return $this->rows[$id] = array_merge($this->rows[$id], $attributes);
    }

    protected function persistDelete(string $id, ApplicationContext $context): bool
    {
        $this->calls[] = 'persistDelete';

        if (! isset($this->rows[$id])) {
            return false;
        }

        $this->deleted[$id] = $this->rows[$id];
        unset($this->rows[$id]);

        return true;
    }

    protected function persistRestore(string $id, ApplicationContext $context): ?array
    {
        $this->calls[] = 'persistRestore';

        if (! isset($this->deleted[$id])) {
            return null;
        }

        return $this->rows[$id] = $this->deleted[$id];
    }
}

/**
 * Same fake, but declaring the full surface including restore.
 */
final class RestorableFakeProductRepository extends FakeProductRepository
{
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
}

final class FakeQueryExecutor implements QueryExecutor
{
    /** @var list<QueryOptions> */
    public array $received = [];

    public function execute(QueryOptions $options, ApplicationContext $context): QueryResult
    {
        $this->received[] = $options;

        return new QueryResult([['id' => 1]]);
    }
}

/**
 * Stand-in for a persistence row type in definitions (never instantiated).
 */
final class FakeProductRow
{
}