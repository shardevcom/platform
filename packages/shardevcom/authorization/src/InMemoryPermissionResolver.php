<?php

declare(strict_types=1);

namespace Shardevcom\Authorization;

use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\Contracts\PermissionResolver;
use Shardevcom\Core\ResourceAction;

/**
 * Explicit grant list resolver (T-031).
 *
 * Default binding: deny-by-default (empty list). Tests, seeds and local
 * harnesses inject grants like ['products.view', 'products.*'].
 * Production projects swap this binding for Gate/Spatie/external without
 * touching repositories or the AuthorizationManager.
 */
final class InMemoryPermissionResolver implements PermissionResolver
{
    /**
     * @param list<string> $granted e.g. ['products.view', 'products.*']
     */
    public function __construct(private array $granted = [])
    {
    }

    public function can(ApplicationContext $context, string $resource, ResourceAction|string $action): bool
    {
        $permission = ResourceAction::permission($resource, $action);

        return in_array($permission, $this->granted, true)
            || in_array($resource.'.*', $this->granted, true);
    }
}
