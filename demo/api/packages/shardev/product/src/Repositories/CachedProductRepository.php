<?php

declare(strict_types=1);

namespace Shardevcom\Product\Repositories;

use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\Contracts\CacheStore;
use Shardevcom\Core\Contracts\QueryExecutor;
use Shardevcom\Core\Lifecycle\ActionLifecycle;
use Shardevcom\Core\Repositories\AbstractResourceRepository;
use Shardevcom\Resources\ResourceDefinition;

/**
 * Reference cache decorator (T-121) — NOT wired by default.
 *
 * This class exists as a compilable example of the documented
 * Repo → Cache → Persistence pattern. Bind it in the container in place of the
 * plain repository only when read caching is actually needed:
 *
 *     $this->app->singleton(ProductRepository::class, function ($app) {
 *         return new CachedProductRepository(
 *             new ProductRepository($app->make(ActionLifecycle::class), $app->make(QueryExecutor::class)),
 *             $app->make(CacheStore::class),
 *             $app->make(ActionLifecycle::class),
 *             $app->make(QueryExecutor::class),
 *         );
 *     });
 *
 * Authorization stays in exactly one place: every public operation is
 * delegated to the inner repository, so the lifecycle funnel — and therefore
 * authorization and hooks — still runs once, inside the inner repository. The
 * cache is a pure read-through layer with explicit invalidation on writes; it
 * is never a second authorization path.
 *
 * The inner repository does not know it is being cached.
 */
final class CachedProductRepository extends AbstractResourceRepository
{
    private const TTL_SECONDS = 300;

    public function __construct(
        private readonly AbstractResourceRepository $inner,
        private readonly CacheStore $cache,
        ActionLifecycle $lifecycle,
        QueryExecutor $executor,
    ) {
        parent::__construct($lifecycle, $executor);
    }

    public function resourceName(): string
    {
        return $this->inner->resourceName();
    }

    public function resourceDefinition(): ResourceDefinition
    {
        return $this->inner->resourceDefinition();
    }

    /**
     * @return list<string>
     */
    public function supportedOperations(): array
    {
        return $this->inner->supportedOperations();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string|int $id, ApplicationContext $context): ?array
    {
        $key = $this->key($id);
        $cached = $this->cache->get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $row = $this->inner->find($id, $context);

        if ($row !== null) {
            $this->cache->put($key, $row, self::TTL_SECONDS);
        }

        return $row;
    }

    /**
     * @param list<string> $includes
     * @return array<string, mixed>
     */
    public function findOrFail(string|int $id, ApplicationContext $context, array $includes = []): array
    {
        return $this->inner->findOrFail($id, $context, $includes);
    }

    /**
     * @param list<string|int> $ids
     * @return list<array<string, mixed>>
     */
    public function findMany(array $ids, ApplicationContext $context): array
    {
        return $this->inner->findMany($ids, $context);
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function create(array $attributes, ApplicationContext $context): array
    {
        return $this->inner->create($attributes, $context);
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function update(string|int $id, array $attributes, ApplicationContext $context): array
    {
        $row = $this->inner->update($id, $attributes, $context);
        $this->cache->forget($this->key($id));

        return $row;
    }

    public function delete(string|int $id, ApplicationContext $context): void
    {
        $this->inner->delete($id, $context);
        $this->cache->forget($this->key($id));
    }

    /**
     * @return array<string, mixed>
     */
    public function restore(string|int $id, ApplicationContext $context): array
    {
        $row = $this->inner->restore($id, $context);
        $this->cache->forget($this->key($id));

        return $row;
    }

    private function key(string|int $id): string
    {
        return $this->resourceName().':'.$id;
    }

    // Persistence primitives ---------------------------------------------------
    // The public surface above delegates to the inner repository, so these are
    // never reached in normal use; the base class requires them and they keep
    // the decorator a valid, substitutable subclass.

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>|null
     */
    protected function persistNew(array $attributes, ApplicationContext $context): ?array
    {
        return $this->inner->create($attributes, $context);
    }

    /**
     * @param list<string> $includes
     * @return array<string, mixed>|null
     */
    protected function persistFind(string $id, ApplicationContext $context, array $includes = []): ?array
    {
        return $this->inner->find($id, $context);
    }

    /**
     * @param list<string> $ids
     * @return list<array<string, mixed>>
     */
    protected function persistFindMany(array $ids, ApplicationContext $context): array
    {
        return $this->inner->findMany($ids, $context);
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>|null
     */
    protected function persistUpdate(string $id, array $attributes, ApplicationContext $context): ?array
    {
        return $this->inner->update($id, $attributes, $context);
    }

    protected function persistDelete(string $id, ApplicationContext $context): bool
    {
        $this->inner->delete($id, $context);

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function persistRestore(string $id, ApplicationContext $context): ?array
    {
        return $this->inner->restore($id, $context);
    }
}
