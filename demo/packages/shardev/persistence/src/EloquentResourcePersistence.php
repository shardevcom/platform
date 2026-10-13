<?php

declare(strict_types=1);

namespace Shardevcom\Persistence;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\Exceptions\Conflict;
use Shardevcom\Core\Exceptions\ValidationFailed;
use Shardevcom\Resources\ResourceDefinition;
use Shardevcom\Resources\ResourceScope;
use Throwable;

/**
 * Eloquent implementation of the six persistence primitives declared by
 * AbstractResourceRepository (T-052).
 *
 * A TRAIT living in this package (never in Core), so the ORM stays an opt-in
 * adapter: repositories persisting elsewhere implement the primitives
 * themselves. Core keeps depending on ports and plain rows only.
 *
 * Guarantees added on top of the abstract base:
 * - mass assignment through the model's $fillable via fill(), so a model that
 *   forgot to declare a column simply does not accept it;
 * - writes may only touch columns the definition declares, so no payload can
 *   invent a column even when the model would accept it;
 * - tenant/owner constraints from ResourceScope (T-032) apply to every read,
 *   update and delete: a context can never reach another tenant's row by
 *   guessing an id, and a row hidden by scope is reported exactly like an
 *   absent one.
 *
 * Transactions are deliberately NOT opened here. A single primitive is one
 * statement; wrapping it would add a round trip and hide an implicit
 * boundary. When a Use Case must span several writes atomically, it opens the
 * transaction itself so the commit boundary stays visible in business code:
 *
 *     // Use Case (T-060), never a repository: the boundary is the workflow.
 *     DB::transaction(function () use ($dto, $context) {
 *         $product = $this->products->create($dto->attributes(), $context);
 *         $this->stock->reserve($product['id'], $dto->quantity(), $context);
 *     });
 *
 * The calls above still go through the repository, so authorization and hooks
 * run inside the transaction: opening one is not a way to bypass the access
 * layer.
 */
trait EloquentResourcePersistence
{
    /**
     * Definition backing this repository. Declared here so Core stays unaware
     * of resource metadata plumbing.
     */
    abstract public function resourceDefinition(): ResourceDefinition;

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    protected function persistNew(array $attributes, ApplicationContext $context): array
    {
        $attributes = $this->rejectUndeclared($attributes);
        $attributes = $this->withScopeColumns($attributes, $context);

        $model = $this->newModel();
        $model->fill($attributes);

        try {
            $model->save();
        } catch (QueryException $e) {
            // A driver exception would leak SQL and driver internals to the
            // client. Integrity violations are a legitimate domain outcome
            // (duplicate key, bad reference), so they become Conflict and the
            // original stays as the cause for logs.
            throw self::translateWriteFailure($this->resourceDefinition()->name(), $e);
        }

        return $this->toRow($model);
    }

    /**
     * Maps an integrity violation onto Conflict, anything else passes through
     * untouched so real faults stay visible.
     */
    protected static function translateWriteFailure(string $resource, QueryException $e): Throwable
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');

        $integrityStates = ['23000', '23505', '23503', '23514', '23001'];

        if (in_array($sqlState, $integrityStates, true)) {
            return new Conflict(
                "The write on resource [{$resource}] violates a data integrity rule.",
                ['resource' => $resource],
                $e,
            );
        }

        return $e;
    }

    /**
     * @param list<string> $includes relations already validated against the
     *                                 resource definition
     * @return array<string, mixed>|null
     */
    protected function persistFind(string $id, ApplicationContext $context, array $includes = []): ?array
    {
        $query = $this->scopedQuery($context, $id);

        if ($includes !== []) {
            $query->with($includes);
        }

        $model = $query->first();

        return $model instanceof Model ? $this->toRow($model) : null;
    }

    /**
     * @param list<string> $ids
     * @return list<array<string, mixed>>
     */
    protected function persistFindMany(array $ids, ApplicationContext $context): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = [];

        foreach ($this->scopedQuery($context)->whereIn($this->keyName(), $ids)->get() as $model) {
            $rows[(string) $model->getAttribute($this->keyName())] = $this->toRow($model);
        }

        // Request order is the caller's contract, not the database's.
        $ordered = [];

        foreach ($ids as $id) {
            if (isset($rows[$id])) {
                $ordered[] = $rows[$id];
            }
        }

        return $ordered;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>|null
     */
    protected function persistUpdate(string $id, array $attributes, ApplicationContext $context): ?array
    {
        $model = $this->scopedQuery($context, $id)->first();

        if (! $model instanceof Model) {
            return null;
        }

        $model->fill($this->rejectUndeclared($attributes));

        try {
            $model->save();
        } catch (QueryException $e) {
            throw self::translateWriteFailure($this->resourceDefinition()->name(), $e);
        }

        return $this->toRow($model);
    }

    protected function persistDelete(string $id, ApplicationContext $context): bool
    {
        $model = $this->scopedQuery($context, $id)->first();

        if (! $model instanceof Model) {
            return false;
        }

        // SoftDeletes models archive, plain models remove. Both are "gone" for
        // this context, which is all the abstract base needs to report.
        $model->delete();

        return true;
    }

    /**
     * @return array<string, mixed>|null null when the row is gone for good
     */
    protected function persistRestore(string $id, ApplicationContext $context): ?array
    {
        // withTrashed() only exists when the model opts into SoftDeletes, so
        // the check must come first: a hard-deleting model simply has nothing
        // restorable.
        if (! $this->isSoftDeleting($this->newModel())) {
            return null;
        }

        $model = $this->scopedQuery($context, $id)->withTrashed()->first();

        if (! $model instanceof Model) {
            return null;
        }

        $model->restore();

        return $this->toRow($model);
    }

    /**
     * Scoped query for one id, or for the whole visible set when $id is null.
     *
     * @return Builder<Model>
     */
    protected function scopedQuery(ApplicationContext $context, ?string $id = null): Builder
    {
        $query = $this->modelClass()::query();

        foreach (ResourceScope::for($this->resourceDefinition(), $context)->constraints() as $column => $value) {
            $query->where($column, $value);
        }

        return $id === null ? $query : $query->where($this->keyName(), $id);
    }

    /**
     * @return class-string<Model>
     */
    protected function modelClass(): string
    {
        $modelClass = $this->resourceDefinition()->modelClass();

        if (! is_string($modelClass) || ! is_a($modelClass, Model::class, true)) {
            throw new \RuntimeException(
                "Resource [{$this->resourceDefinition()->name()}] has no persistence model."
            );
        }

        return $modelClass;
    }

    protected function keyName(): string
    {
        return $this->newModel()->getKeyName();
    }

    protected function newModel(): Model
    {
        $modelClass = $this->modelClass();

        return new $modelClass;
    }

    /**
     * Rows leave the persistence layer as plain arrays (T-051 boundary).
     *
     * @return array<string, mixed>
     */
    protected function toRow(Model $model): array
    {
        return $model->toArray();
    }

    protected function isSoftDeleting(Model $model): bool
    {
        return in_array(SoftDeletes::class, class_uses($model) ?: [], true);
    }

    /**
     * Writes may only touch columns the definition declares. Client input
     * never invents a column, not even a fillable one.
     *
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    protected function rejectUndeclared(array $attributes): array
    {
        $declared = $this->resourceDefinition()->fields();

        if ($declared === []) {
            return $attributes;
        }

        $allowed = array_merge($declared, [$this->keyName()]);

        $rejected = array_values(array_filter(
            array_keys($attributes),
            static fn (string $field): bool => ! in_array($field, $allowed, true),
        ));

        if ($rejected !== []) {
            throw new ValidationFailed(
                array_fill_keys($rejected, 'This field is not writable for this resource.'),
                'Rejected fields: '.implode(', ', $rejected).'.',
            );
        }

        return $attributes;
    }

    /**
     * A tenant scoped row is always stamped from the context: a payload can
     * never choose which tenant it writes into (T-032).
     *
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    protected function withScopeColumns(array $attributes, ApplicationContext $context): array
    {
        if (! $this->resourceDefinition()->tenantScoped()) {
            return $attributes;
        }

        if ($context->tenantId() === null) {
            throw new Conflict(
                'A tenant scoped resource cannot be written without a tenant in the context.',
                ['resource' => $this->resourceDefinition()->name()],
            );
        }

        $attributes['tenant_id'] = $context->tenantId();

        return $attributes;
    }
}