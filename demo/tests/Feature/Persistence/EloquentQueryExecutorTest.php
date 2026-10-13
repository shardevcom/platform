<?php

declare(strict_types=1);

namespace Tests\Feature\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\Exceptions\ValidationFailed;
use Shardevcom\Persistence\EloquentQueryExecutor;
use Shardevcom\Resources\QueryOptions;
use Shardevcom\Resources\ResourceDefinition;
use Tests\TestCase;

/**
 * T-041/T-042 acceptance on sqlite: validated options become scoped,
 * filtered, searched, sorted, paginated queries with eager includes
 * (no N+1) and field-projected rows.
 */
class ExecutorBrand extends Model
{
    protected $table = 'executor_brands';

    protected $guarded = [];

    public $timestamps = false;
}

class ExecutorArticle extends Model
{
    protected $table = 'executor_articles';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['active' => 'bool'];

    public function brand()
    {
        return $this->belongsTo(ExecutorBrand::class, 'brand_id');
    }
}

final class EloquentQueryExecutorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('executor_articles');
        Schema::dropIfExists('executor_brands');

        Schema::create('executor_brands', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('executor_articles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('tenant_id')->nullable();
            $table->unsignedInteger('brand_id')->nullable();
            $table->string('sku');
            $table->string('name');
            $table->boolean('active')->default(true);
        });

        $brand = ExecutorBrand::create(['name' => 'Acme']);

        ExecutorArticle::create(['tenant_id' => 't-A', 'brand_id' => $brand->id, 'sku' => 'WID-001', 'name' => 'Widget', 'active' => true]);
        ExecutorArticle::create(['tenant_id' => 't-A', 'brand_id' => $brand->id, 'sku' => 'GAD-002', 'name' => 'Gadget', 'active' => true]);
        ExecutorArticle::create(['tenant_id' => 't-A', 'brand_id' => null, 'sku' => 'OLD-003', 'name' => 'Obsolete', 'active' => false]);
        ExecutorArticle::create(['tenant_id' => 't-B', 'brand_id' => null, 'sku' => 'WID-004', 'name' => 'Widget B', 'active' => true]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('executor_articles');
        Schema::dropIfExists('executor_brands');

        parent::tearDown();
    }

    private function definition(): ResourceDefinition
    {
        return ResourceDefinition::make('articles')
            ->withFilters(['active', 'brand_id'])
            ->withIncludes(['brand'])
            ->withSorting(['name'])
            ->withFields(['id', 'sku', 'name', 'active', 'brand_id'])
            ->withSearchable(['sku', 'name'])
            ->withTenantScope()
            ->withModel(ExecutorArticle::class);
    }

    private function context(array $overrides = []): ApplicationContext
    {
        return ApplicationContext::create(array_merge(['tenantId' => 't-A'], $overrides));
    }

    public function test_filters_apply_as_wheres_with_tenant_scope(): void
    {
        $result = (new EloquentQueryExecutor())->execute(
            QueryOptions::create($this->definition(), ['filters' => ['active' => true]]),
            $this->context()
        );

        $this->assertSame(2, count($result->items()));
        $this->assertSame(2, $result->pagination()->total);
    }

    public function test_where_in_for_list_values(): void
    {
        $result = (new EloquentQueryExecutor())->execute(
            QueryOptions::create($this->definition(), ['filters' => ['brand_id' => [1]]]),
            $this->context()
        );

        $this->assertSame(2, count($result->items()));
    }

    public function test_tenant_isolation(): void
    {
        $result = (new EloquentQueryExecutor())->execute(
            QueryOptions::create($this->definition()),
            $this->context(['tenantId' => 't-B'])
        );

        $this->assertSame(['WID-004'], array_column($result->items(), 'sku'));
    }

    public function test_undeclared_filter_never_reaches_the_database(): void
    {
        $this->expectException(ValidationFailed::class);
        QueryOptions::create($this->definition(), ['filters' => ['hacked' => 1]]);
    }

    public function test_undeclared_include_never_reaches_the_database(): void
    {
        $this->expectException(ValidationFailed::class);
        QueryOptions::create($this->definition(), ['includes' => ['images']]);
    }

    public function test_search_matches_declared_columns(): void
    {
        $result = (new EloquentQueryExecutor())->execute(
            QueryOptions::create($this->definition(), ['search' => 'wid']),
            $this->context()
        );

        $this->assertSame(['WID-001'], array_column($result->items(), 'sku'));
    }

    public function test_sort_applies_declared_direction(): void
    {
        $result = (new EloquentQueryExecutor())->execute(
            QueryOptions::create($this->definition(), ['sort' => ['-name']]),
            $this->context()
        );

        $this->assertSame(['Widget', 'Obsolete', 'Gadget'], array_column($result->items(), 'name'));
    }

    public function test_pagination_meta(): void
    {
        $result = (new EloquentQueryExecutor())->execute(
            QueryOptions::create($this->definition(), ['per_page' => 2]),
            $this->context()
        );

        $this->assertSame(2, count($result->items()));
        $this->assertSame(3, $result->pagination()->total);
        $this->assertSame(2, $result->pagination()->lastPage());
    }

    public function test_includes_eager_load_without_n_plus_one(): void
    {
        DB::enableQueryLog();

        $result = (new EloquentQueryExecutor())->execute(
            QueryOptions::create($this->definition(), ['includes' => ['brand']]),
            $this->context()
        );

        // count + select + one eager load, regardless of row count.
        $this->assertLessThanOrEqual(3, count(DB::getQueryLog()));
        $this->assertSame('Acme', $result->items()[0]['brand']['name']);

        DB::disableQueryLog();
    }

    public function test_fields_project_output_rows(): void
    {
        $result = (new EloquentQueryExecutor())->execute(
            QueryOptions::create($this->definition(), ['fields' => ['id', 'name'], 'per_page' => 1]),
            $this->context()
        );

        $this->assertSame(['id', 'name'], array_keys($result->items()[0]));
    }

    public function test_missing_model_is_a_server_misconfiguration(): void
    {
        $this->expectException(\RuntimeException::class);
        (new EloquentQueryExecutor())->execute(
            QueryOptions::create(ResourceDefinition::make('plain')),
            $this->context()
        );
    }
}
