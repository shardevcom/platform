<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Shardevcom\Authorization\AuthorizationManager;
use Shardevcom\Core\Contracts\AuthorizationManager as ManagerContract;
use Shardevcom\Core\Contracts\PermissionResolver as ResolverContract;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\Exceptions\Forbidden;
use Shardevcom\Core\ResourceAction;
use Tests\TestCase;

/**
 * T-030/T-031 acceptance in the generated app: container bindings resolve
 * to shared singletons with deny-by-default semantics.
 */
final class AuthorizationBindingTest extends TestCase
{
    public function test_manager_and_resolver_are_shared_singletons(): void
    {
        $this->assertInstanceOf(AuthorizationManager::class, $this->app->make(ManagerContract::class));
        $this->assertSame($this->app->make(ManagerContract::class), $this->app->make(ManagerContract::class));
        $this->assertSame($this->app->make(ResolverContract::class), $this->app->make(ResolverContract::class));
    }

    public function test_default_binding_denies_before_any_persistence(): void
    {
        $this->expectException(Forbidden::class);
        $this->app->make(ManagerContract::class)->authorize(
            'products',
            ResourceAction::VIEW,
            ApplicationContext::create()
        );
    }
}
