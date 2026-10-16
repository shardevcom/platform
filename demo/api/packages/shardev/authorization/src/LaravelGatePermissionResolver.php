<?php

declare(strict_types=1);

namespace Shardevcom\Authorization;

use Illuminate\Support\Facades\Gate;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\Contracts\PermissionResolver;
use Shardevcom\Core\ResourceAction;

/**
 * Laravel Gate backed resolver (T-031).
 *
 * Delegates to Gate abilities named like the permission string
 * ('products.update'), receiving the context as the ability argument.
 * Undefined abilities deny by default. Projects define abilities in
 * AuthServiceProvider or swap this binding entirely.
 */
final class LaravelGatePermissionResolver implements PermissionResolver
{
    public function can(ApplicationContext $context, string $resource, ResourceAction|string $action): bool
    {
        return Gate::allows(ResourceAction::permission($resource, $action), [$context]);
    }
}
