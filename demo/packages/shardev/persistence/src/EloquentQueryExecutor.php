<?php

declare(strict_types=1);

namespace Shardevcom\Persistence;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\Contracts\QueryExecutor;
use Shardevcom\Resources\QueryOptions;
use Shardevcom\Resources\QueryResult;
use Shardevcom\Resources\ResourceScope;
use Shardevcom\Core\Results\PaginationMeta;

/**
 * Eloquent translation of validated QueryOptions (T-041).
 *
 * This adapter is the ONLY place allowed to touch Eloquent query building:
 * options arrive pre-validated against the definition, so no client input
 * can inject columns, relations, directions or raw fragments. Scope
 * constraints (T-032) apply as plain wheres; search uses escaped LIKE over
 * declared searchable columns; includes eager-load (no N+1); field
 * projection happens on output arrays so selects never break eager loads.
 */
final class EloquentQueryExecutor implements QueryExecutor
{
    public function execute(QueryOptions $options, ApplicationContext $context): QueryResult
    {
        $definition = $options->definition();
        $modelClass = $definition->modelClass();

        if (! is_string($modelClass) || ! is_a($modelClass, Model::class, true)) {
            throw new \RuntimeException("Resource [{$definition->name()}] has no queryable Eloquent model.");
        }

        /** @var Builder<Model> $query */
        $query = $modelClass::query();

        foreach (ResourceScope::for($definition, $context)->constraints() as $column => $value) {
            $query->where($column, $value);
        }

        foreach ($options->filters()->all() as $field => $value) {
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }

        $this->applySearch($query, $options);
        $this->applyIncludes($query, $options);
        $this->applySort($query, $options);

        $pagination = $options->pagination();

        if ($pagination->isUnlimited()) {
            $records = $query->get();
            $meta = PaginationMeta::unlimited((int) $records->count());
        } else {
            $paginator = $query->paginate($pagination->perPage, ['*'], 'page', $pagination->page);
            $records = $paginator->items();
            $meta = new PaginationMeta($paginator->currentPage(), $paginator->perPage(), (int) $paginator->total());
        }

        // Column minimisation and relation loading are two different
        // allow-lists: a projection that dropped the relations the caller was
        // explicitly allowed to ask for would be a silent data loss, not a
        // security win (IncludeSet already validated them).
        $keep = $options->fields()->all() === []
            ? []
            : [...$options->fields()->all(), ...$options->includes()->all()];

        $items = [];

        foreach ($records as $model) {
            $row = $model->toArray();
            $items[] = $keep === [] ? $row : array_intersect_key($row, array_flip($keep));
        }

        return new QueryResult($items, $meta);
    }

    private function applySearch(Builder $query, QueryOptions $options): void
    {
        $search = $options->search();

        if ($search === null) {
            return;
        }

        $columns = $options->definition()->searchable();
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        $query->where(function (Builder $nested) use ($columns, $like): void {
            foreach ($columns as $column) {
                $nested->orWhere($column, 'like', $like);
            }
        });
    }

    private function applyIncludes(Builder $query, QueryOptions $options): void
    {
        $includes = $options->includes()->all();

        if ($includes !== []) {
            $query->with($includes);
        }
    }

    private function applySort(Builder $query, QueryOptions $options): void
    {
        foreach ($options->sort()->all() as $field => $direction) {
            $query->orderBy($field, $direction);
        }
    }
}
