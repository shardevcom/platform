<?php

declare(strict_types=1);

namespace Shardevcom\Core\Contracts;

use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\ResourceAction;

/**
 * Boundary: storage of permission facts, swappable without touching
 * repositories. The AuthorizationManager asks here; whether answers come
 * from Laravel Gate, Spatie, an external RBAC/ABAC service or an in-memory
 * fake is an infrastructure detail behind this contract.
 *
 * Accepts ResourceAction|string (see AuthorizationManager) for custom
 * module operations.
 */
interface PermissionResolver
{
    public function can(ApplicationContext $context, string $resource, ResourceAction|string $action): bool;
}
