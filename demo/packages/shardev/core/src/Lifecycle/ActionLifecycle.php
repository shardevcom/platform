<?php

declare(strict_types=1);

namespace Shardevcom\Core\Lifecycle;

use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\Contracts\AuthorizationManager;
use Shardevcom\Core\ResourceAction;
use Throwable;

/**
 * Lifecycle gate every public resource operation goes through (T-050).
 *
 * Fixed order: before hooks → authorize → operation (validate + persist) →
 * after hooks. The authorization decision is made HERE, not in the operation,
 * so there is exactly one place where "authorized" happens and a repository
 * cannot reach persistence without it.
 *
 * The operation receives the context and returns whatever the caller needs
 * (DTO, entity array, QueryResult). The lifecycle stays transport-neutral:
 * no HTTP, no Eloquent, no container.
 */
final class ActionLifecycle
{
    public function __construct(
        private AuthorizationManager $authorization,
        private LifecycleHooks $hooks = new HookChain(),
    ) {
    }

    /**
     * @param array<string, mixed> $attributes safe, loggable echoes passed to hooks
     */
    public function execute(
        string $resource,
        ResourceAction|string $action,
        ApplicationContext $context,
        callable $operation,
        array $attributes = [],
    ): mixed {
        $invocation = ActionInvocation::forAction($resource, $action, $context, $attributes);

        $this->hooks->beforeAction($invocation);

        try {
            $this->authorization->authorize($resource, $action, $context);

            $result = $operation($context);
        } catch (Throwable $failure) {
            $this->hooks->onActionFailure($invocation, $failure);

            throw $failure;
        }

        $this->hooks->afterAction($invocation, $result);

        return $result;
    }
}