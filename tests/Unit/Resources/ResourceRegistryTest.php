<?php

declare(strict_types=1);

namespace Tests\Unit\Resources;

use Shardevcom\Core\Contracts\ResourceRegistry as RegistryContract;
use Shardevcom\Core\Exceptions\Conflict;
use Shardevcom\Core\Exceptions\ResourceNotFound;
use Shardevcom\Resources\InMemoryResourceRegistry;
use Shardevcom\Resources\ResourceDefinition;
use PHPUnit\Framework\TestCase;

/**
 * T-021 acceptance: register/get/has/all with domain failures.
 */
final class ResourceRegistryTest extends TestCase
{
    public function test_implements_the_core_contract(): void
    {
        $this->assertInstanceOf(RegistryContract::class, new InMemoryResourceRegistry());
    }

    public function test_register_and_resolve(): void
    {
        $registry = new InMemoryResourceRegistry();
        $definition = ResourceDefinition::make('products')->withFilters(['sku']);

        $this->assertFalse($registry->has('products'));

        $registry->register($definition);

        $this->assertTrue($registry->has('products'));
        $this->assertSame($definition, $registry->get('products'));
        $this->assertSame(['products' => $definition], $registry->all());
    }

    public function test_duplicate_registration_is_a_conflict(): void
    {
        $registry = new InMemoryResourceRegistry();
        $registry->register(ResourceDefinition::make('products'));

        $this->expectException(Conflict::class);
        $registry->register(ResourceDefinition::make('products'));
    }

    public function test_unknown_lookup_is_a_domain_not_found(): void
    {
        $registry = new InMemoryResourceRegistry();

        $this->expectException(ResourceNotFound::class);
        $registry->get('unknown');
    }
}
