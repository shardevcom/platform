<?php

declare(strict_types=1);

namespace Tests\Unit\Resources;

use Shardevcom\Core\Contracts\ResourceRepository;
use Shardevcom\Core\ResourceAction;
use Shardevcom\Resources\ResourceDefinition;
use PHPUnit\Framework\TestCase;

/**
 * T-020 acceptance: validated, immutable, allow-list oriented definitions.
 */
final class ResourceDefinitionTest extends TestCase
{
    public function test_make_builds_fluent_immutable_definition(): void
    {
        $base = ResourceDefinition::make('products');
        $built = $base
            ->withActions([ResourceAction::VIEW, ResourceAction::CREATE, 'publish'])
            ->withFilters(['sku', 'active'])
            ->withIncludes(['brand'])
            ->withSorting(['name'])
            ->withFields(['id', 'name']);

        $this->assertSame([], $base->filters());
        $this->assertSame('products', $built->name());
        $this->assertSame(['view', 'create', 'publish'], $built->actions());
        $this->assertSame(['sku', 'active'], $built->filters());

        $this->assertTrue($built->allowsFilter('sku'));
        $this->assertFalse($built->allowsFilter('hacked'));
        $this->assertTrue($built->allowsInclude('brand'));
        $this->assertFalse($built->allowsInclude('images'));
        $this->assertTrue($built->allowsSort('name'));
        $this->assertTrue($built->allowsField('id'));
        $this->assertTrue($built->supportsAction(ResourceAction::VIEW));
        $this->assertTrue($built->supportsAction('publish'));
        $this->assertFalse($built->supportsAction(ResourceAction::DELETE));
    }

    public function test_invalid_names_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ResourceDefinition::make('Products.List');
    }

    public function test_duplicate_filters_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ResourceDefinition::make('products')->withFilters(['sku', 'sku']);
    }

    public function test_unknown_repository_class_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ResourceDefinition::make('products')->withRepository('Nope\\Missing');
    }

    public function test_repository_must_implement_the_contract(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ResourceDefinition::make('products')->withRepository(\stdClass::class);
    }

    public function test_contract_implementation_is_accepted(): void
    {
        $definition = ResourceDefinition::make('products')->withRepository(FakeProductRepository::class);

        $this->assertSame(FakeProductRepository::class, $definition->repositoryClass());
    }
}

final class FakeProductRepository implements ResourceRepository
{
    public function resourceName(): string
    {
        return 'products';
    }

    public function supportedOperations(): array
    {
        return ['find'];
    }
}
