<?php

declare(strict_types=1);

namespace Shardevcom\Resources;

use Shardevcom\Core\Exceptions\ValidationFailed;

/**
 * Validated query envelope (T-040, caps T-042).
 *
 * Built from one ResourceDefinition plus raw client input; every set
 * validates against the definition allow-lists at construction, so the
 * persistence layer (T-041) never sees an undeclared filter, include,
 * sort or field. Search requires declared searchable columns.
 *
 * Safety caps apply by default and clamp/throw BEFORE persistence:
 * per_page clamps to max_per_page, oversized include/filter counts throw
 * ValidationFailed.
 */
final class QueryOptions
{
    public const DEFAULT_MAX_PER_PAGE = 100;

    public const DEFAULT_MAX_INCLUDES = 5;

    public const DEFAULT_MAX_FILTERS = 20;

    public const MAX_SEARCH_LENGTH = 255;

    private function __construct(
        private ResourceDefinition $definition,
        private FilterSet $filters,
        private IncludeSet $includes,
        private SortSet $sort,
        private Pagination $pagination,
        private FieldSet $fields,
        private ?string $search,
    ) {
    }

    /**
     * @param array{filters?: array<string, mixed>, includes?: string|list<string>, sort?: list<string>, page?: int, per_page?: int, fields?: list<string>, search?: ?string, action?: string, pagination?: bool} $input
     * @param array{max_per_page?: int, max_includes?: int, max_filters?: int} $caps
     */
    public static function create(ResourceDefinition $definition, array $input = [], array $caps = []): self
    {
        $maxPerPage = $caps['max_per_page'] ?? self::DEFAULT_MAX_PER_PAGE;
        $maxIncludes = $caps['max_includes'] ?? self::DEFAULT_MAX_INCLUDES;
        $maxFilters = $caps['max_filters'] ?? self::DEFAULT_MAX_FILTERS;

        $filters = FilterSet::fromArray($input['filters'] ?? [], $definition);

        if ($filters->count() > $maxFilters) {
            throw new ValidationFailed(['filters' => ["At most {$maxFilters} filters allowed."]]);
        }

        $includesInput = $input['includes'] ?? [];
        $includes = is_string($includesInput)
            ? IncludeSet::fromString($includesInput, $definition)
            : IncludeSet::fromArray($includesInput, $definition);

        if ($includes->count() > $maxIncludes) {
            throw new ValidationFailed(['includes' => ["At most {$maxIncludes} includes allowed."]]);
        }

        $sort = SortSet::fromArray($input['sort'] ?? [], $definition);
        $pagination = ($input['pagination'] ?? null) === false
            ? Pagination::unlimited()
            : Pagination::make($input['page'] ?? 1, $input['per_page'] ?? 15)->capped($maxPerPage);

        // The action decides which projection may leave the boundary. For
        // list/select that projection is a fixed allow-list on the resource,
        // not a suggestion: the client may narrow it with ?fields= but never
        // widen it, because a holder of "{resource}.list" is supposed to see
        // exactly the minimised column set (T-CASA-015). Every other action
        // falls back to the resource's own field allow-list, so a column the
        // resource never declared cannot leave through an empty ?fields=.
        $action = $input['action'] ?? 'view';
        $requestedFields = $input['fields'] ?? [];

        $fields = ($action === 'list' || $action === 'select')
            ? self::projectionAllowList($requestedFields, $definition, $action)
            : FieldSet::fromArray($requestedFields ?: $definition->effectiveFields($action), $definition);

        $search = self::validateSearch($input['search'] ?? null, $definition);

        return new self($definition, $filters, $includes, $sort, $pagination, $fields, $search);
    }

    public function definition(): ResourceDefinition
    {
        return $this->definition;
    }

    public function filters(): FilterSet
    {
        return $this->filters;
    }

    public function includes(): IncludeSet
    {
        return $this->includes;
    }

    public function sort(): SortSet
    {
        return $this->sort;
    }

    public function pagination(): Pagination
    {
        return $this->pagination;
    }

    public function fields(): FieldSet
    {
        return $this->fields;
    }

    public function search(): ?string
    {
        return $this->search;
    }

    /**
     * Projection the client is allowed to receive for a list/select action.
     *
     * The resource declares it once; the request can only ask for a subset of
     * it. Asking for anything wider is a validation failure rather than a
     * silent drop, so an attempt to read past the minimisation shows up as an
     * error instead of as an empty column (T-CASA-015).
     *
     * @param list<string> $requested
     */
    private static function projectionAllowList(array $requested, ResourceDefinition $definition, string $action): FieldSet
    {
        $allowList = $definition->effectiveFields($action);

        if ($requested === []) {
            return FieldSet::fromArray($allowList, $definition);
        }

        $errors = [];

        foreach ($requested as $field) {
            if (! is_string($field) || ! in_array($field, $allowList, true)) {
                $errors['fields.'.$field] = ["Field is not part of the [{$action}] projection."];
            }
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        return FieldSet::fromArray($requested, $definition);
    }

    private static function validateSearch(mixed $search, ResourceDefinition $definition): ?string
    {
        if ($search === null || $search === '') {
            return null;
        }

        if (! is_string($search)) {
            throw new ValidationFailed(['search' => ['Search must be a string.']]);
        }

        $search = trim($search);

        if (mb_strlen($search) > self::MAX_SEARCH_LENGTH) {
            throw new ValidationFailed(['search' => ['Search is too long.']]);
        }

        if ($definition->searchable() === []) {
            throw new ValidationFailed(['search' => ['Search is not supported by this resource.']]);
        }

        return $search;
    }
}
