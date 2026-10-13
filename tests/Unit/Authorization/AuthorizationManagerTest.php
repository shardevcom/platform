<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use Shardevcom\Authorization\AuthorizationManager;
use Shardevcom\Authorization\InMemoryPermissionResolver;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\Contracts\PermissionResolver;
use Shardevcom\Core\Exceptions\Forbidden;
use Shardevcom\Core\ResourceAction;
use PHPUnit\Framework\TestCase;

/**
 * T-030 acceptance: one decision point, deny-before-persistence semantics.
 */
final class AuthorizationManagerTest extends TestCase
{
    public function test_granted_action_passes_silently(): void
    {
        $manager = new AuthorizationManager(new InMemoryPermissionResolver(['products.update']));

        // Must not throw.
        $manager->authorize('products', ResourceAction::UPDATE, ApplicationContext::create());

        $this->assertTrue(true);
    }

    public function test_denied_action_throws_forbidden_with_safe_context(): void
    {
        $manager = new AuthorizationManager(new InMemoryPermissionResolver());

        try {
            $manager->authorize('products', ResourceAction::UPDATE, ApplicationContext::create([
                'userId' => 'u-1',
                'tenantId' => 't-1',
            ]));
            $this->fail('Forbidden was not thrown.');
        } catch (Forbidden $e) {
            $this->assertSame('FORBIDDEN', $e->errorCode());
            $this->assertSame('products', $e->context()['resource']);
            $this->assertSame('update', $e->context()['action']);
            $this->assertSame('u-1', $e->context()['user']);
            $this->assertSame('t-1', $e->context()['tenant']);
        }
    }

    public function test_custom_string_action_is_supported(): void
    {
        $manager = new AuthorizationManager(new InMemoryPermissionResolver(['products.publish']));

        $manager->authorize('products', 'publish', ApplicationContext::create());

        $this->assertTrue(true);
    }

    public function test_wildcard_grant_covers_all_actions(): void
    {
        $manager = new AuthorizationManager(new InMemoryPermissionResolver(['products.*']));

        $manager->authorize('products', ResourceAction::DELETE, ApplicationContext::create());

        $this->assertTrue(true);
    }

    public function test_resolver_is_swappable_behind_the_contract(): void
    {
        $denyAll = new class implements PermissionResolver
        {
            public function can(ApplicationContext $c, string $r, ResourceAction|string $a): bool
            {
                return false;
            }
        };

        $this->expectException(Forbidden::class);
        (new AuthorizationManager($denyAll))->authorize('products', ResourceAction::VIEW, ApplicationContext::create());
    }
}
