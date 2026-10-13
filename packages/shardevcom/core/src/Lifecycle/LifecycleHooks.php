<?php

declare(strict_types=1);

namespace Shardevcom\Core\Lifecycle;

use Throwable;

/**
 * Boundary: cross-cutting observation points around an authorized resource
 * operation — audit trail, domain events, metrics, cache invalidation.
 *
 * Deliberately NOT one of the seven Shardevcom\Core\Contracts: those are the
 * platform provider boundaries bound in the container, while hooks are the
 * lifecycle's own extension point, always injected into ActionLifecycle.
 *
 * Rules for implementations:
 * - GENERIC. No module business logic, no resource names, no query knowledge.
 *   Audit/event/metric concerns get one implementation each, combined by
 *   HookChain. Business logic belongs in Use Cases (T-060), never in a hook.
 * - Hooks MUST NOT throw. Swallowing an audit failure silently would be worse
 *   than surfacing it, so failures propagate instead of being absorbed here.
 */
interface LifecycleHooks
{
    /**
     * Runs BEFORE the authorization decision, so denied attempts are observable.
     */
    public function beforeAction(ActionInvocation $invocation): void;

    /**
     * Runs after the operation returned successfully.
     *
     * @param mixed $result the operation return value (DTO, entity, QueryResult, ...)
     */
    public function afterAction(ActionInvocation $invocation, mixed $result): void;

    /**
     * Runs after ANY failure (authorization denial, validation, business rule,
     * persistence). The lifecycle always rethrows the original failure
     * afterwards, unchanged.
     */
    public function onActionFailure(ActionInvocation $invocation, Throwable $failure): void;
}