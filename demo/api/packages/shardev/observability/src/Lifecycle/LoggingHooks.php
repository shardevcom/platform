<?php

declare(strict_types=1);

namespace Shardevcom\Observability\Lifecycle;

use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\Contracts\Logger;
use Shardevcom\Core\Lifecycle\ActionInvocation;
use Shardevcom\Core\Lifecycle\LifecycleHooks;
use Throwable;

/**
 * T-CASA-023 lifecycle half of the structured trace.
 *
 * Emits one line per authorized resource operation (before/after counts).
 * Every line carries the SAME correlation/request ids as the request line the
 * middleware wrote, so the acceptance property "correlation_id present across
 * request, lifecycle and error" holds by construction in the filtering union.
 *
 * Dimensions come from the invocation itself, never from the container or a
 * request — this is what keeps the hook honest on CLI/job paths too.
 */
final class LoggingHooks implements LifecycleHooks
{
    private float $startedAt = 0.0;

    public function __construct(private readonly Logger $logger)
    {
    }

    public function beforeAction(ActionInvocation $invocation): void
    {
        $this->startedAt = microtime(true);
    }

    public function afterAction(ActionInvocation $invocation, mixed $result): void
    {
        $this->logger->info(
            'Resource action completed',
            $this->dimensions($invocation) + ['duration_ms' => $this->elapsed()]
        );
    }

    public function onActionFailure(ActionInvocation $invocation, Throwable $failure): void
    {
        $this->logger->error(
            'Resource action failed',
            $this->dimensions($invocation)
            + ['duration_ms' => $this->elapsed(), 'failure' => $failure::class]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function dimensions(ActionInvocation $invocation): array
    {
        $context = $invocation->context();

        return [
            'correlation_id' => $context->correlationId(),
            'request_id' => $context->requestId(),
            'user_id' => $context->userId(),
            'tenant_id' => $context->tenantId(),
            'guard' => $context->guard(),
            'action' => $invocation->action(),
            'resource' => $invocation->resource(),
        ];
    }

    private function elapsed(): float
    {
        return $this->startedAt > 0.0 ? round((microtime(true) - $this->startedAt) * 1000, 3) : 0.0;
    }
}