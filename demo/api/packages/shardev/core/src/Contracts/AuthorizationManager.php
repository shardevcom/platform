<?php

declare(strict_types=1);

namespace Shardevcom\Core\Contracts;

use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\ResourceAction;

/**
 * Boundary: single decision point for "may this context perform this action
 * on this resource". Repositories ask here BEFORE touching persistence, so
 * REST, GraphQL, CLI and Jobs share exactly one authorization rule set.
 *
 * Throws Shardevcom\Core\Exceptions\Forbidden (T-013) on denial, never a boolean
 * that callers could ignore, and never an HTTP exception.
 *
 * The action accepts ResourceAction|string so modules can authorize custom
 * operations (validated by ResourceAction::segment) without editing Core.
 */
interface AuthorizationManager
{
    public function authorize(string $resource, ResourceAction|string $action, ApplicationContext $context): void;
}
