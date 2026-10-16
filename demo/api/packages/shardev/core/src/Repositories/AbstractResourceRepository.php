<?php

declare(strict_types=1);

namespace Shardevcom\Core\Repositories;

use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\Contracts\QueryExecutor;
use Shardevcom\Core\Contracts\ResourceRepository as ResourceRepositoryContract;
use Shardevcom\Core\Exceptions\Conflict;
use Shardevcom\Core\Exceptions\ResourceNotFound;
use Shardevcom\Core\Exceptions\ResourceOperationNotSupported;
use Shardevcom\Core\Lifecycle\ActionLifecycle;
use Shardevcom\Core\ResourceAction;
use Shardevcom\Resources\IncludeSet;
use Shardevcom\Resources\QueryOptions;
use Shardevcom\Resources\QueryResult;

/**
 * Base authorized access point for a resource (T-051).
 *
 * This class owns the access rules, NOT the data:
 * - every public operation passes through ActionLifecycle::execute(), so
 *   authorization and hooks cannot be skipped (T-050);
 * - the operation surface is explicit: supportedOperations() declares what
 *   the subclass really implements and anything else throws
 *   ResourceOperationNotSupported instead of silently half-working;
 * - rows cross the boundary as plain arrays (array<string,mixed>) so Core
 *   stays free of any persistence engine, HTTP and container concerns.
 *   Adapters (T-070, T-073) and Use Cases (T-060) reshape them into
 *   DTOs/responses.
 *
 * Subclasses implement ONLY the persistence primitives below: a driver
 * adapter (T-052) or an in-memory fake in tests. Primitives receive the
 * ApplicationContext so the adapter can apply the tenant/owner scope from
 * ResourceScope (T-032).
 *
 * Validation of client input is NOT repeated here: QueryOptions is already
 * allow-list validated (T-040) and attribute rules live in the Use Case DTO
 * (T-060). One rule set per concern, not two.
 *
 * Transactions are intentionally NOT opened here: opening one is a Use Case
 * decision (T-060), never a hidden side effect of a single call.
 *
 * @phpstan-type Attributes array<string, mixed>
 * @phpstan-type Row array<string, mixed>
 */
abstract class AbstractResourceRepository implements ResourceRepositoryContract
{
    public const CREATE = 'create';

    public const FIND = 'find';

    public const FIND_OR_FAIL = 'findOrFail';

    public const FIND_MANY = 'findMany';

    public const QUERY = 'query';

    public const LIST = 'list';

    public const UPDATE = 'update';

    public const DELETE = 'delete';

    public const RESTORE = 'restore';

    public const IMPORT = 'import';

    public const EXPORT = 'export';

    /**
     * Declared support. Narrow it in the subclass to forbid operations whose
     * persistence primitive is not implemented.
     *
     * @var list<string>
     */
    protected array $supportedOperations = [
        self::CREATE,
        self::FIND,
        self::FIND_OR_FAIL,
        self::FIND_MANY,
        self::QUERY,
        self::LIST,
        self::UPDATE,
        self::DELETE,
        self::IMPORT,
        self::EXPORT,
    ];

    public function __construct(
        protected ActionLifecycle $lifecycle,
        protected QueryExecutor $queryExecutor,
    ) {
    }

    abstract public function resourceName(): string;

    /**
     * @return list<string>
     */
    public function supportedOperations(): array
    {
        return $this->supportedOperations;
    }

    public function supportsOperation(string $operation): bool
    {
        return in_array($operation, $this->supportedOperations, true);
    }

    /**
     * @param Attributes $attributes
     * @return Row
     */
    public function create(array $attributes, ApplicationContext $context): array
    {
        $this->assertSupported(self::CREATE);

        return $this->execute(
            ResourceAction::CREATE,
            $context,
            fn (): ?array => $this->persistNew($attributes, $context),
            ['fields' => array_keys($attributes)],
        ) ?? throw new Conflict(
            'The created row could not be read back.',
            ['resource' => $this->resourceName()],
        );
    }

    /**
     * @return Row|null null when absent or invisible to this context
     */
    public function find(string|int $id, ApplicationContext $context): ?array
    {
        $this->assertSupported(self::FIND);
        $id = $this->normalizeId($id);

        return $this->execute(
            ResourceAction::VIEW,
            $context,
            fn (): ?array => $this->persistFind($id, $context),
            ['id' => $id],
        );
    }

    /**
     * @param list<string> $includes relations to eager-load, validated against
     *                                  the definition so a client cannot name an
     *                                  arbitrary relationship
     * @return Row
     */
    public function findOrFail(string|int $id, ApplicationContext $context, array $includes = []): array
    {
        $this->assertSupported(self::FIND_OR_FAIL);
        $id = $this->normalizeId($id);
        $includes = IncludeSet::fromArray(array_values($includes), $this->resourceDefinition())->all();

        return $this->execute(
            ResourceAction::VIEW,
            $context,
            function () use ($id, $context, $includes): array {
                return $this->persistFind($id, $context, $includes)
                    ?? throw ResourceNotFound::for($this->resourceName(), $id);
            },
            ['id' => $id],
        );
    }

    /**
     * Missing ids are simply absent from the result.
     *
     * @param list<string|int> $ids
     * @return list<array<string, mixed>>
     */
    public function findMany(array $ids, ApplicationContext $context): array
    {
        $this->assertSupported(self::FIND_MANY);
        $ids = array_values(array_map($this->normalizeId(...), $ids));

        return $ids === []
            ? []
            : $this->execute(
                ResourceAction::VIEW,
                $context,
                fn (): array => $this->persistFindMany($ids, $context),
                ['ids' => $ids],
            );
    }

    /**
     * QueryOptions is already allow-list validated (T-040), so this method
     * adds no validation of its own.
     */
    public function query(QueryOptions $options, ApplicationContext $context): QueryResult
    {
        $this->assertSupported(self::QUERY);

        return $this->execute(
            ResourceAction::VIEW,
            $context,
            fn (): QueryResult => $this->queryExecutor->execute($options, $context),
        );
    }

    /**
     * List resources with select fields (for dropdowns, autocomplete, etc.).
     *
     * Uses the 'list' action which applies selectFields from the definition
     * instead of all fields. Requires 'products.list' permission.
     */
    public function list(QueryOptions $options, ApplicationContext $context): QueryResult
    {
        $this->assertSupported(self::LIST);

        return $this->execute(
            ResourceAction::segment('list'),
            $context,
            fn (): QueryResult => $this->queryExecutor->execute($options, $context),
        );
    }

    /**
     * Export (T-CASA-015): a query operation that authorizes {resource}.export
     * instead of {resource}.view, so a reader role with view only cannot bulk
     * extract data. The projection follows the same QueryOptions allow-lists.
     */
    public function export(QueryOptions $options, ApplicationContext $context): QueryResult
    {
        $this->assertSupported(self::EXPORT);

        return $this->execute(
            ResourceAction::segment('export'),
            $context,
            fn (): QueryResult => $this->queryExecutor->execute($options, $context),
        );
    }

    /**
     * Import (T-CASA-015): the lifecycle gate for a bulk ingestion workflow.
     *
     * The import itself is not a row operation this repository can persist, so
     * it hands the authorized operation back to the use case that owns the
     * workflow (file validation, session, queue). Authorization is still
     * decided HERE, in the same single funnel as every other resource action.
     *
     * @template T
     * @param callable(ApplicationContext):T $operation
     * @return T
     */
    public function import(ApplicationContext $context, callable $operation): mixed
    {
        $this->assertSupported(self::IMPORT);

        return $this->execute(
            ResourceAction::segment('import'),
            $context,
            $operation,
        );
    }

    /**
     * @param Attributes $attributes
     * @return Row
     */
    public function update(string|int $id, array $attributes, ApplicationContext $context): array
    {
        $this->assertSupported(self::UPDATE);
        $id = $this->normalizeId($id);

        return $this->execute(
            ResourceAction::UPDATE,
            $context,
            function () use ($id, $attributes, $context): array {
                return $this->persistUpdate($id, $attributes, $context)
                    ?? throw ResourceNotFound::for($this->resourceName(), $id);
            },
            ['id' => $id, 'fields' => array_keys($attributes)],
        );
    }

    /**
     * Absent (or invisible) rows raise ResourceNotFound, so adapters never
     * report a silent success on a no-op.
     */
    public function delete(string|int $id, ApplicationContext $context): void
    {
        $this->assertSupported(self::DELETE);
        $id = $this->normalizeId($id);

        $this->execute(
            ResourceAction::DELETE,
            $context,
            function () use ($id, $context): null {
                if ($this->persistDelete($id, $context) !== true) {
                    throw ResourceNotFound::for($this->resourceName(), $id);
                }

                return null;
            },
            ['id' => $id],
        );
    }

    /**
     * @return Row
     */
    public function restore(string|int $id, ApplicationContext $context): array
    {
        $this->assertSupported(self::RESTORE);
        $id = $this->normalizeId($id);

        return $this->execute(
            ResourceAction::RESTORE,
            $context,
            function () use ($id, $context): array {
                return $this->persistRestore($id, $context)
                    ?? throw ResourceNotFound::for($this->resourceName(), $id);
            },
            ['id' => $id],
        );
    }

    /**
     * Persistence primitive: insert one row and return it as stored.
     *
     * @param Attributes $attributes
     * @return array<string, mixed>|null null only for a legitimately rejected insert
     */
    abstract protected function persistNew(array $attributes, ApplicationContext $context): ?array;

    /**
     * Persistence primitive: read one row, honouring the tenant/owner scope.
     *
     * @return array<string, mixed>|null
     */
    /**
     * @param list<string> $includes
     */
    abstract protected function persistFind(string $id, ApplicationContext $context, array $includes = []): ?array;

    /**
     * @param list<string> $ids
     * @return list<array<string, mixed>>
     */
    abstract protected function persistFindMany(array $ids, ApplicationContext $context): array;

    /**
     * Persistence primitive: apply the patch and return the stored row.
     *
     * @param Attributes $attributes
     * @return array<string, mixed>|null null when the row is absent or invisible
     */
    abstract protected function persistUpdate(string $id, array $attributes, ApplicationContext $context): ?array;

    /**
     * Persistence primitive: remove one row.
     *
     * @return bool false when the row is absent or invisible
     */
    abstract protected function persistDelete(string $id, ApplicationContext $context): bool;

    /**
     * Persistence primitive: bring a soft-deleted row back.
     *
     * @return array<string, mixed>|null null when the row is absent or not restorable
     */
    abstract protected function persistRestore(string $id, ApplicationContext $context): ?array;

    /**
     * Single funnel for every public operation (T-050).
     *
     * @param array<string, mixed> $attributes safe, loggable echo for hooks
     */
    protected function execute(
        ResourceAction|string $action,
        ApplicationContext $context,
        callable $operation,
        array $attributes = [],
    ): mixed {
        return $this->lifecycle->execute(
            $this->resourceName(),
            $action,
            $context,
            $operation,
            $attributes,
        );
    }

    protected function assertSupported(string $operation): void
    {
        if (! $this->supportsOperation($operation)) {
            throw ResourceOperationNotSupported::for($this->resourceName(), $operation);
        }
    }

    protected function normalizeId(string|int $id): string
    {
        $id = (string) $id;

        if (trim($id) === '') {
            throw ResourceNotFound::for($this->resourceName(), '(empty)');
        }

        return $id;
    }
}