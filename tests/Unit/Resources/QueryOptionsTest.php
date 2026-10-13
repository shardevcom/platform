<?php

declare(strict_types=1);

namespace Tests\Unit\Resources;

use Shardevcom\Core\Exceptions\ValidationFailed;
use Shardevcom\Resources\QueryOptions;
use Shardevcom\Resources\ResourceDefinition;
use PHPUnit\Framework\TestCase;

/**
 * T-040/T-042 acceptance: validated sets, allow-list enforcement, caps.
 */
final class QueryOptionsTest extends TestCase
{
    private function definition(): ResourceDefinition
    {
        return ResourceDefinition::make('products')
            ->withFilters(['sku', 'active', 'brand_id'])
            ->withIncludes(['brand'])
            ->withSorting(['name', 'created_at'])
            ->withFields(['id', 'name', 'active'])
            ->withSearchable(['sku', 'name']);
    }

    public function test_defaults_are_sane(): void
    {
        $options = QueryOptions::create($this->definition());

        $this->assertSame([], $options->filters()->all());
        $this->assertSame([], $options->includes()->all());
        $this->assertSame([], $options->sort()->all());
        $this->assertSame(1, $options->pagination()->page);
        $this->assertSame(15, $options->pagination()->perPage);
        // The default projection is the resource's own allow-list, so "no
        // fields requested" and "all declared fields" are the same answer and
        // an undeclared column cannot leave through an empty ?fields=.
        $this->assertSame(['id', 'name', 'active'], $options->fields()->all());
        $this->assertNull($options->search());
    }

    public function test_valid_input_builds_all_sets(): void
    {
        $options = QueryOptions::create($this->definition(), [
            'filters' => ['active' => true, 'brand_id' => [1, 2]],
            'includes' => 'brand',
            'sort' => ['name', '-created_at'],
            'page' => 2,
            'per_page' => 10,
            'fields' => ['id', 'name'],
            'search' => 'wid',
        ]);

        $this->assertSame(['active' => true, 'brand_id' => [1, 2]], $options->filters()->all());
        $this->assertSame(['brand'], $options->includes()->all());
        $this->assertSame(['name' => 'asc', 'created_at' => 'desc'], $options->sort()->all());
        $this->assertSame(2, $options->pagination()->page);
        $this->assertSame(10, $options->pagination()->perPage);
        $this->assertSame(['id', 'name'], $options->fields()->all());
        $this->assertSame('wid', $options->search());
    }

    public function test_undeclared_filter_is_rejected(): void
    {
        $this->expectException(ValidationFailed::class);
        QueryOptions::create($this->definition(), ['filters' => ['hacked' => 1]]);
    }

    public function test_nested_filter_value_is_rejected(): void
    {
        $this->expectException(ValidationFailed::class);
        QueryOptions::create($this->definition(), ['filters' => ['sku' => ['nested' => 'x']]]);
    }

    public function test_undeclared_include_is_rejected(): void
    {
        $this->expectException(ValidationFailed::class);
        QueryOptions::create($this->definition(), ['includes' => ['images']]);
    }

    public function test_undeclared_sort_is_rejected(): void
    {
        $this->expectException(ValidationFailed::class);
        QueryOptions::create($this->definition(), ['sort' => ['-password']]);
    }

    public function test_undeclared_field_is_rejected(): void
    {
        $this->expectException(ValidationFailed::class);
        QueryOptions::create($this->definition(), ['fields' => ['password']]);
    }

    /**
     * T-CASA-015: list/select answer exactly the declared select-field
     * projection, and ?fields can only narrow it.
     */
    public function test_list_defaults_to_the_declared_projection(): void
    {
        $definition = $this->definition()->withSelectFields(['id', 'name']);

        $this->assertSame(['id', 'name'], QueryOptions::create($definition, ['action' => 'list'])->fields()->all());
    }

    public function test_list_allows_narrowing_inside_the_projection(): void
    {
        $definition = $this->definition()->withSelectFields(['id', 'name']);

        $this->assertSame(['name'], QueryOptions::create($definition, ['action' => 'list', 'fields' => ['name']])->fields()->all());
    }

    public function test_list_rejects_a_field_outside_the_projection(): void
    {
        $definition = $this->definition()->withSelectFields(['id', 'name']);

        $this->expectException(ValidationFailed::class);
        QueryOptions::create($definition, ['action' => 'list', 'fields' => ['active']]);
    }

    public function test_select_action_behaves_like_list_for_the_projection(): void
    {
        $definition = $this->definition()->withSelectFields(['id', 'name']);

        $this->assertSame(['id', 'name'], QueryOptions::create($definition, ['action' => 'select'])->fields()->all());
    }

    public function test_invalid_pagination_is_rejected(): void
    {
        $this->expectException(ValidationFailed::class);
        QueryOptions::create($this->definition(), ['page' => 0]);
    }

    public function test_per_page_clamps_to_max(): void
    {
        $options = QueryOptions::create($this->definition(), ['per_page' => 9999]);

        $this->assertSame(QueryOptions::DEFAULT_MAX_PER_PAGE, $options->pagination()->perPage);
    }

    public function test_caps_are_overridable(): void
    {
        $options = QueryOptions::create($this->definition(), ['per_page' => 50], ['max_per_page' => 10]);

        $this->assertSame(10, $options->pagination()->perPage);
    }

    public function test_too_many_includes_are_rejected(): void
    {
        $definition = $this->definition()->withIncludes(['a', 'b', 'c', 'd', 'e', 'f']);

        $this->expectException(ValidationFailed::class);
        QueryOptions::create($definition, ['includes' => ['a', 'b', 'c', 'd', 'e', 'f']]);
    }

    public function test_search_without_searchable_is_rejected(): void
    {
        $this->expectException(ValidationFailed::class);
        QueryOptions::create(ResourceDefinition::make('plain'), ['search' => 'x']);
    }

    public function test_oversized_search_is_rejected(): void
    {
        $this->expectException(ValidationFailed::class);
        QueryOptions::create($this->definition(), ['search' => str_repeat('a', 256)]);
    }
}
