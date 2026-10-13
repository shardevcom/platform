<?php

declare(strict_types=1);

namespace Tests\Unit\Authentication;

use PHPUnit\Framework\TestCase;
use Shardevcom\Core\Authentication\EffectivePermissions;
use Shardevcom\Resources\InMemoryResourceRegistry;
use Shardevcom\Resources\ResourceDefinition;

/**
 * T-CASA-008 acceptance: listing a token's effective permissions is a
 * supported authentication contract operation, and permissions stay
 * action-granular (no bare '*' in the result).
 */
final class EffectivePermissionsTest extends TestCase
{
    public function test_wildcard_is_expanded_to_every_registered_action(): void
    {
        $registry = $this->registry();

        $effective = EffectivePermissions::from(['products.*'], $registry);

        $this->assertSame(['products.create', 'products.delete', 'products.view'], $effective);
        $this->assertNotContains('products.*', $effective);
    }

    public function test_literal_permissions_are_kept_verbatim(): void
    {
        $registry = $this->registry();

        $effective = EffectivePermissions::from(['products.view', 'products.import', 'configuration.admin'], $registry);

        $this->assertSame(['configuration.admin', 'products.import', 'products.view'], $effective);
    }

    public function test_mixing_wildcard_and_literals_does_not_duplicate(): void
    {
        $registry = $this->registry();

        $effective = EffectivePermissions::from(['products.*', 'products.view', 'products.delete'], $registry);

        $this->assertSame(['products.create', 'products.delete', 'products.view'], $effective);
    }

    public function test_unknown_resource_wildcards_are_not_silently_dropped(): void
    {
        $registry = $this->registry();

        $effective = EffectivePermissions::from(['orders.*', 'products.view'], $registry);

        $this->assertSame(['orders.*', 'products.view'], $effective);
    }

    public function test_a_deprecated_global_star_expands_across_all_resources(): void
    {
        $registry = $this->registry();
        $registry->register(ResourceDefinition::make('billing')->withActions(['invoice', 'refund']));

        $effective = EffectivePermissions::from(['*'], $registry);

        $this->assertSame(['billing.invoice', 'billing.refund', 'products.create', 'products.delete', 'products.view'], $effective);
    }

    public function test_empty_token_grants_nothing(): void
    {
        $this->assertSame([], EffectivePermissions::from([], $this->registry()));
    }

    private function registry(): InMemoryResourceRegistry
    {
        $registry = new InMemoryResourceRegistry();

        $registry->register(
            ResourceDefinition::make('products')
                ->withActions(['view', 'create', 'delete'])
        );

        return $registry;
    }
}