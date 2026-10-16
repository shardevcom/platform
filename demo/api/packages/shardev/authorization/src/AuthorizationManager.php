<?php

declare(strict_types=1);

namespace Shardevcom\Authorization;

use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\Contracts\AuthorizationManager as ManagerContract;
use Shardevcom\Core\Contracts\PermissionResolver;
use Shardevcom\Core\Exceptions\Forbidden;
use Shardevcom\Core\ResourceAction;

/**
 * Central authorization decision point (T-030).
 *
 * Repositories call here BEFORE persistence; adapters never authorize.
 * Denial throws Forbidden (never a silently ignorable boolean). Storage
 * of permission facts stays behind PermissionResolver, so this class never
 * changes when projects move from config to Spatie, Gate or an
 * external RBAC/ABAC service.
 */
final class AuthorizationManager implements ManagerContract
{
    public function __construct(private PermissionResolver $resolver)
    {
    }

    public function authorize(string $resource, ResourceAction|string $action, ApplicationContext $context): void
    {
        if (! $this->resolver->can($context, $resource, $action)) {
            $segment = ResourceAction::segment($action);

            throw new Forbidden(
                "Action [{$segment}] on resource [{$resource}] is forbidden.",
                [
                    'resource' => $resource,
                    'action' => $segment,
                    'user' => $context->userId(),
                    'tenant' => $context->tenantId(),
                ]
            );
        }
    }
}
