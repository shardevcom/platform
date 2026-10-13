<?php

declare(strict_types=1);

namespace Shardevcom\Resources;

use Shardevcom\Core\Contracts\ResourceRepository;
use Shardevcom\Core\ResourceAction;

/**
 * Allow-listed metadata of one addressable resource (T-020).
 *
 * Deliberately a single validated value object instead of a metadata class
 * graph: name, actions, filters, includes, sorting, fields, repository and
 * policy. Per-field rich metadata (types, casts) can grow into dedicated
 * VOs later — today that would be ceremony without a consumer (T-040 only
 * needs membership checks, provided below).
 *
 * The repository class stays optional so a definition (T-022) can exist
 * before its repository (T-053); registration requires it (T-053).
 */
final class ResourceDefinition
{
    /**
     * @param list<string> $actions permission segments (canonical or custom)
     * @param list<string> $filters
     * @param list<string> $includes
     * @param list<string> $sorting
     * @param list<string> $fields
     * @param list<string> $selectFields fields returned for list/select operations
     */
    private function __construct(
        private string $name,
        private array $actions = [],
        private array $filters = [],
        private array $includes = [],
        private array $sorting = [],
        private array $fields = [],
        private array $selectFields = [],
        private ?string $repositoryClass = null,
        private ?string $policy = null,
        private bool $tenantScoped = false,
        private ?string $ownerKey = null,
        private array $searchable = [],
        private ?string $modelClass = null,
    ) {
        self::assertName($name);

        foreach (['filters' => $filters, 'includes' => $includes, 'sorting' => $sorting, 'fields' => $fields, 'selectFields' => $selectFields] as $kind => $values) {
            self::assertUniqueStrings($kind, $values);
        }

        foreach ($actions as $action) {
            ResourceAction::segment($action);
        }

        if ($repositoryClass !== null) {
            self::assertRepository($repositoryClass);
        }
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    /**
     * @param list<ResourceAction|string> $actions
     */
    public function withActions(array $actions): self
    {
        $clone = clone $this;
        $clone->actions = array_values(array_unique(array_map(
            static fn (ResourceAction|string $a): string => ResourceAction::segment($a),
            $actions
        )));

        return $clone;
    }

    /**
     * @param list<string> $filters
     */
    public function withFilters(array $filters): self
    {
        $clone = clone $this;
        self::assertUniqueStrings('filters', $filters);
        $clone->filters = array_values($filters);

        return $clone;
    }

    /**
     * @param list<string> $includes
     */
    public function withIncludes(array $includes): self
    {
        $clone = clone $this;
        self::assertUniqueStrings('includes', $includes);
        $clone->includes = array_values($includes);

        return $clone;
    }

    /**
     * @param list<string> $sorting
     */
    public function withSorting(array $sorting): self
    {
        $clone = clone $this;
        self::assertUniqueStrings('sorting', $sorting);
        $clone->sorting = array_values($sorting);

        return $clone;
    }

    /**
     * @param list<string> $fields
     */
    public function withFields(array $fields): self
    {
        $clone = clone $this;
        self::assertUniqueStrings('fields', $fields);
        $clone->fields = array_values($fields);

        return $clone;
    }

    /**
     * Fields returned for list/select operations (subset of fields).
     * Used when the action is 'list' or 'select' to return only
     * essential fields for dropdowns, autocomplete, etc.
     *
     * @param list<string> $selectFields
     */
    public function withSelectFields(array $selectFields): self
    {
        $clone = clone $this;
        self::assertUniqueStrings('selectFields', $selectFields);

        // Validate that selectFields are a subset of fields
        $unknown = array_diff($selectFields, $this->fields);
        if ($unknown !== []) {
            throw new \InvalidArgumentException(
                "Select fields must be a subset of fields. Unknown: ".implode(', ', $unknown)
            );
        }

        $clone->selectFields = array_values($selectFields);

        return $clone;
    }

    /**
     * @return list<string>
     */
    public function selectFields(): array
    {
        return $this->selectFields;
    }

    /**
     * Get the effective fields for a given action.
     * Returns selectFields for 'list'/'select' actions, otherwise all fields.
     *
     * @return list<string>
     */
    public function effectiveFields(ResourceAction|string $action): array
    {
        $segment = ResourceAction::segment($action);

        if (($segment === 'list' || $segment === 'select') && $this->selectFields !== []) {
            return $this->selectFields;
        }

        return $this->fields;
    }

    /**
     * @param class-string $repositoryClass
     */
    public function withRepository(string $repositoryClass): self
    {
        self::assertRepository($repositoryClass);
        $clone = clone $this;
        $clone->repositoryClass = $repositoryClass;

        return $clone;
    }

    public function withPolicy(string $policy): self
    {
        $clone = clone $this;
        $clone->policy = $policy;

        return $clone;
    }

    /**
     * Opt-in: rows are partitioned by tenant. The tenant ALWAYS comes from
     * ApplicationContext::tenantId() — never derived from user_id (T-032).
     */
    public function withTenantScope(bool $tenantScoped = true): self
    {
        $clone = clone $this;
        $clone->tenantScoped = $tenantScoped;

        return $clone;
    }

    /**
     * Opt-in: rows belong to a user id stored under this column.
     */
    public function withOwnerKey(?string $ownerKey): self
    {
        if ($ownerKey !== null && $ownerKey === '') {
            throw new \InvalidArgumentException('Owner key must be a non-empty column name or null.');
        }

        $clone = clone $this;
        $clone->ownerKey = $ownerKey;

        return $clone;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return list<string>
     */
    public function actions(): array
    {
        return $this->actions;
    }

    /**
     * @return list<string>
     */
    public function filters(): array
    {
        return $this->filters;
    }

    /**
     * @return list<string>
     */
    public function includes(): array
    {
        return $this->includes;
    }

    /**
     * @return list<string>
     */
    public function sorting(): array
    {
        return $this->sorting;
    }

    /**
     * @return list<string>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    public function repositoryClass(): ?string
    {
        return $this->repositoryClass;
    }

    public function policy(): ?string
    {
        return $this->policy;
    }

    public function tenantScoped(): bool
    {
        return $this->tenantScoped;
    }

    public function ownerKey(): ?string
    {
        return $this->ownerKey;
    }

    /**
     * Columns the free-text search may match (T-040). Empty disables search.
     *
     * @param list<string> $searchable
     */
    public function withSearchable(array $searchable): self
    {
        $clone = clone $this;
        self::assertUniqueStrings('searchable', $searchable);
        $clone->searchable = array_values($searchable);

        return $clone;
    }

    /**
     * Eloquent model backing this resource (T-041). Validated for existence
     * here; the Eloquent type check lives in the persistence adapter so this
     * package stays Eloquent-free.
     *
     * @param class-string $modelClass
     */
    public function withModel(string $modelClass): self
    {
        if (! class_exists($modelClass)) {
            throw new \InvalidArgumentException("Model [{$modelClass}] does not exist.");
        }

        $clone = clone $this;
        $clone->modelClass = $modelClass;

        return $clone;
    }

    /**
     * @return list<string>
     */
    public function searchable(): array
    {
        return $this->searchable;
    }

    public function modelClass(): ?string
    {
        return $this->modelClass;
    }

    public function allowsFilter(string $filter): bool
    {
        return in_array($filter, $this->filters, true);
    }

    public function allowsInclude(string $include): bool
    {
        return in_array($include, $this->includes, true);
    }

    public function allowsSort(string $sort): bool
    {
        return in_array($sort, $this->sorting, true);
    }

    public function allowsField(string $field): bool
    {
        return in_array($field, $this->fields, true);
    }

    public function supportsAction(ResourceAction|string $action): bool
    {
        return in_array(ResourceAction::segment($action), $this->actions, true);
    }

    private static function assertName(string $name): void
    {
        if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $name) !== 1) {
            throw new \InvalidArgumentException("Invalid resource name [{$name}].");
        }
    }

    /**
     * @param list<string> $values
     */
    private static function assertUniqueStrings(string $kind, array $values): void
    {
        foreach ($values as $value) {
            if (! is_string($value) || $value === '') {
                throw new \InvalidArgumentException("Resource {$kind} must be non-empty strings.");
            }
        }

        if (count($values) !== count(array_unique($values))) {
            throw new \InvalidArgumentException("Resource {$kind} must not contain duplicates.");
        }
    }

    private static function assertRepository(string $repositoryClass): void
    {
        if (! class_exists($repositoryClass) || ! is_a($repositoryClass, ResourceRepository::class, true)) {
            throw new \InvalidArgumentException(
                "Repository [{$repositoryClass}] must exist and implement ResourceRepository."
            );
        }
    }
}
