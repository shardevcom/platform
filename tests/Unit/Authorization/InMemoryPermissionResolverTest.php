<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use Shardevcom\Authorization\InMemoryPermissionResolver;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\ResourceAction;
use PHPUnit\Framework\TestCase;

/**
 * T-031 acceptance: explicit grants, deny-by-default, wildcard support.
 */
final class InMemoryPermissionResolverTest extends TestCase
{
    public function test_deny_by_default(): void
    {
        $resolver = new InMemoryPermissionResolver();

        $this->assertFalse($resolver->can(ApplicationContext::create(), 'products', ResourceAction::VIEW));
    }

    public function test_exact_grant_allows(): void
    {
        $resolver = new InMemoryPermissionResolver(['products.view']);

        $this->assertTrue($resolver->can(ApplicationContext::create(), 'products', ResourceAction::VIEW));
        $this->assertFalse($resolver->can(ApplicationContext::create(), 'products', ResourceAction::DELETE));
        $this->assertFalse($resolver->can(ApplicationContext::create(), 'orders', ResourceAction::VIEW));
    }

    public function test_wildcard_grant_allows_any_action_on_resource(): void
    {
        $resolver = new InMemoryPermissionResolver(['products.*']);

        $this->assertTrue($resolver->can(ApplicationContext::create(), 'products', 'publish'));
        $this->assertFalse($resolver->can(ApplicationContext::create(), 'orders', ResourceAction::VIEW));
    }
}
