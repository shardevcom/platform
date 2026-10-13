<?php

declare(strict_types=1);

namespace Shardevcom\Product\Graphql\Resolvers;

/**
 * Translates GraphQL list-shaped arguments into the transport-neutral query
 * input the use case already accepts.
 *
 * GraphQL has no map type, so `filter` arrives as a list of {field, value}
 * objects while REST sends a plain object. Only the shape changes here: the
 * allow-list, the caps and the clamping all stay in QueryOptions, so GraphQL
 * cannot validate anything REST would not also validate.
 */
final class ArgumentTranslator
{
    /**
     * @param array<string, mixed> $args
     * @return array{filters?: array<string, mixed>, includes?: string|list<string>, sort?: list<string>, page?: int, per_page?: int, fields?: list<string>}
     */
    public function listQuery(array $args): array
    {
        $query = [];

        if (($filters = $this->filters($args['filter'] ?? [])) !== []) {
            $query['filters'] = $filters;
        }

        if (($includes = $this->strings($args['include'] ?? [])) !== []) {
            $query['includes'] = $includes;
        }

        if (($sort = $this->sort($args['sort'] ?? [])) !== []) {
            $query['sort'] = $sort;
        }

        if (($fields = $this->strings($args['fields'] ?? [])) !== []) {
            $query['fields'] = $fields;
        }

        if (isset($args['page'])) {
            $query['page'] = (int) $args['page'];
        }

        if (isset($args['per_page'])) {
            $query['per_page'] = (int) $args['per_page'];
        }

        return $query;
    }

    /**
     * The core filter vocabulary is `field => value`; there are no operators.
     * Anything else is passed through untouched so QueryOptions rejects it
     * instead of this adapter inventing a second dialect.
     *
     * @param mixed $filters
     * @return array<string, mixed>
     */
    private function filters(mixed $filters): array
    {
        if (! is_array($filters)) {
            return [];
        }

        $translated = [];

        foreach ($filters as $filter) {
            if (! is_array($filter) || ! isset($filter['field'])) {
                return ['filters' => $filters];
            }

            $translated[(string) $filter['field']] = $filter['value'] ?? null;
        }

        return $translated;
    }

    /**
     * @param mixed $sort
     * @return list<string>
     */
    private function sort(mixed $sort): array
    {
        if (! is_array($sort)) {
            return [];
        }

        $translated = [];

        foreach ($sort as $field) {
            if (! is_array($field) || ! isset($field['field'])) {
                return [];
            }

            $translated[] = ($field['direction'] ?? 'asc') === 'desc'
                ? '-'.(string) $field['field']
                : (string) $field['field'];
        }

        return $translated;
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private function strings(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($value) => is_scalar($value) ? (string) $value : null,
            $values,
        ), static fn (?string $value) => $value !== null && $value !== ''));
    }
}