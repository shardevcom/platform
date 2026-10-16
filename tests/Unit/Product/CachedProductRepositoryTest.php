<?php

declare(strict_types=1);

namespace Tests\Unit\Product;

use Shardevcom\Authorization\AuthorizationManager;
use Shardevcom\Authorization\InMemoryPermissionResolver;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\Contracts\CacheStore;
use Shardevcom\Core\Contracts\QueryExecutor;
use Shardevcom\Core\Lifecycle\ActionInvocation;
use Shardevcom\Core\Lifecycle\ActionLifecycle;
use Shardevcom\Core\Lifecycle\LifecycleHooks;
use Shardevcom\Core\Repositories\AbstractResourceRepository;
use Shardevcom\Product\Repositories\CachedProductRepository;
use Shardevcom\Resources\QueryOptions;
use Shardevcom\Resources\QueryResult;
use Shardevcom\Resources\ResourceDefinition;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * T-121 acceptance: the cache decorator documented in the README is a real,
 * compilable class that reads through the cache, invalidates on writes, and
 * keeps authorization in the inner repository (single lifecycle funnel).
 */
final class CachedProductRepositoryTest extends TestCase
{
    public function test_the_decorator_is_a_substitutable_resource_repository(): void
    {
        $decorator = $this->decorator($inner = new RecordingProductRepository($this->lifecycle()));

        $this->assertInstanceOf(AbstractResourceRepository::class, $decorator);
        $this->assertSame('products', $decorator->resourceName());
        $this->assertSame($inner->supportedOperations(), $decorator->supportedOperations());
    }

    public function test_repeated_reads_are_served_from_the_cache(): void
    {
        $inner = new RecordingProductRepository($this->lifecycle());
        $decorator = $this->decorator($inner);
        $context = ApplicationContext::create();

        $inner->create(['sku' => 'A'], $context);

        $first = $decorator->find('1', $context);
        $second = $decorator->find('1', $context);

        $this->assertSame($first, $second);
        $this->assertSame(1, $inner->findCalls, 'the second read must not reach the inner repository');
    }

    public function test_a_write_invalidates_the_cached_row(): void
    {
        $inner = new RecordingProductRepository($this->lifecycle());
        $decorator = $this->decorator($inner);
        $context = ApplicationContext::create();

        $inner->create(['sku' => 'A'], $context);
        $decorator->find('1', $context);

        $decorator->update('1', ['name' => 'Renamed'], $context);
        $refreshed = $decorator->find('1', $context);

        $this->assertSame('Renamed', $refreshed['name']);
        $this->assertSame(2, $inner->findCalls, 'a write must drop the cached row');
    }

    public function test_delete_invalidates_the_cache(): void
    {
        $inner = new RecordingProductRepository($this->lifecycle());
        $decorator = $this->decorator($inner);
        $context = ApplicationContext::create();

        $inner->create(['sku' => 'A'], $context);
        $decorator->find('1', $context);

        $decorator->delete('1', $context);

        $this->assertNull($decorator->find('1', $context));
        $this->assertSame(2, $inner->findCalls, 'a delete must drop the cached row');
    }

    public function test_find_many_delegates_to_the_inner_repository(): void
    {
        $inner = new RecordingProductRepository($this->lifecycle());
        $decorator = $this->decorator($inner);
        $context = ApplicationContext::create();

        $inner->create(['sku' => 'A'], $context);
        $inner->create(['sku' => 'B'], $context);

        $this->assertSame(
            ['B', 'A'],
            array_column($decorator->findMany(['2', '1'], $context), 'sku'),
        );
        $this->assertSame(1, $inner->findManyCalls);
    }

    private function decorator(RecordingProductRepository $inner): CachedProductRepository
    {
        return new CachedProductRepository(
            $inner,
            new InMemoryCacheStore(),
            $this->lifecycle(),
            new NullQueryExecutor(),
        );
    }

    private function lifecycle(): ActionLifecycle
    {
        return new ActionLifecycle(
            new AuthorizationManager(new InMemoryPermissionResolver(['products.*'])),
            new NullHooks(),
        );
    }
}

final class InMemoryCacheStore implements CacheStore
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function put(string $key, mixed $value, int $ttlSeconds): void
    {
        $this->values[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($this->values[$key]);
    }
}

final class NullHooks implements LifecycleHooks
{
    public function beforeAction(ActionInvocation $invocation): void
    {
    }

    public function afterAction(ActionInvocation $invocation, mixed $result): void
    {
    }

    public function onActionFailure(ActionInvocation $invocation, Throwable $failure): void
    {
    }
}

final class NullQueryExecutor implements QueryExecutor
{
    public function execute(QueryOptions $options, ApplicationContext $context): QueryResult
    {
        return new QueryResult([]);
    }
}

/**
 * Minimal in-memory inner repository that counts how often the decorator
 * actually reaches it.
 */
final class RecordingProductRepository extends AbstractResourceRepository
{
    /** @var array<string, array<string, mixed>> */
    private array $rows = [];

    private int $sequence = 0;

    public int $findCalls = 0;

    public int $findManyCalls = 0;

    public function __construct(ActionLifecycle $lifecycle)
    {
        parent::__construct($lifecycle, new NullQueryExecutor());
    }

    public function resourceName(): string
    {
        return 'products';
    }

    public function resourceDefinition(): ResourceDefinition
    {
        return ResourceDefinition::make('products')
            ->withFilters(['sku'])
            ->withFields(['id', 'sku', 'name'])
            ->withSorting(['id'])
            ->withIncludes([]);
    }

    protected function persistNew(array $attributes, ApplicationContext $context): ?array
    {
        $id = (string) ++$this->sequence;

        return $this->rows[$id] = ['id' => $id] + $attributes;
    }

    protected function persistFind(string $id, ApplicationContext $context, array $includes = []): ?array
    {
        $this->findCalls++;

        return $this->rows[$id] ?? null;
    }

    protected function persistFindMany(array $ids, ApplicationContext $context): array
    {
        $this->findManyCalls++;
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
        if (! isset($this->rows[$id])) {
            return null;
        }

        return $this->rows[$id] = array_merge($this->rows[$id], $attributes);
    }

    protected function persistDelete(string $id, ApplicationContext $context): bool
    {
        if (! isset($this->rows[$id])) {
            return false;
        }

        unset($this->rows[$id]);

        return true;
    }

    protected function persistRestore(string $id, ApplicationContext $context): ?array
    {
        return null;
    }
}
