<?php

declare(strict_types=1);

namespace Tests\Unit\Resources;

use Shardevcom\Core\ApplicationContext;
use Shardevcom\Resources\ResourceDefinition;
use Shardevcom\Resources\ResourceScope;
use PHPUnit\Framework\TestCase;

/**
 * T-032 acceptance: tenant/owner constraints, never user-as-tenant.
 */
final class ResourceScopeTest extends TestCase
{
    public function test_tenant_scoped_definition_constrains_by_context_tenant(): void
    {
        $definition = ResourceDefinition::make('products')->withTenantScope();
        $scope = ResourceScope::for($definition, ApplicationContext::create(['tenantId' => 't-A']));

        $this->assertSame(['tenant_id' => 't-A'], $scope->constraints());
        $this->assertFalse($scope->isEmpty());
    }

    public function test_unscoped_definition_ignores_tenant(): void
    {
        $definition = ResourceDefinition::make('products');
        $scope = ResourceScope::for($definition, ApplicationContext::create(['tenantId' => 't-A']));

        $this->assertTrue($scope->isEmpty());
    }

    public function test_user_id_is_never_promoted_to_tenant(): void
    {
        $definition = ResourceDefinition::make('products')->withTenantScope();
        $scope = ResourceScope::for($definition, ApplicationContext::create(['userId' => 'u-7']));

        $this->assertTrue($scope->isEmpty());
        $this->assertArrayNotHasKey('tenant_id', $scope->constraints());
    }

    public function test_owner_key_constrains_by_user(): void
    {
        $definition = ResourceDefinition::make('products')->withOwnerKey('created_by');
        $scope = ResourceScope::for($definition, ApplicationContext::create(['userId' => 'u-7', 'tenantId' => 't-A']));

        $this->assertSame(['created_by' => 'u-7'], $scope->constraints());
    }

    public function test_tenant_and_owner_combine(): void
    {
        $definition = ResourceDefinition::make('products')->withTenantScope()->withOwnerKey('created_by');
        $scope = ResourceScope::for($definition, ApplicationContext::create(['userId' => 'u-7', 'tenantId' => 't-A']));

        $this->assertSame(['tenant_id' => 't-A', 'created_by' => 'u-7'], $scope->constraints());
    }

    public function test_empty_owner_key_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ResourceDefinition::make('products')->withOwnerKey('');
    }
}
