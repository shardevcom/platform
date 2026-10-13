<?php

declare(strict_types=1);

namespace App\Platform\Authorization;

use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\Contracts\PermissionResolver;
use Shardevcom\Core\ResourceAction;

/**
 * Permission resolver that uses the permissions from the ApplicationContext.
 *
 * This is the standard resolver for Bearer token authentication where
 * the token contains the granted abilities.
 */
final class ContextPermissionResolver implements PermissionResolver
{
    public function can(ApplicationContext $context, string $resource, ResourceAction|string $action): bool
    {
        $permission = ResourceAction::permission($resource, $action);
        $granted = $context->permissions();

        // `products.*` grants every action on products, same as the in-memory
        // resolver does. Without this a token issued with a wildcard would be
        // denied every action it was actually given.
        return in_array($permission, $granted, true)
            || in_array($resource.'.*', $granted, true);
    }
}
