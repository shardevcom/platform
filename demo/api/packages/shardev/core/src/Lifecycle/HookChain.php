<?php

declare(strict_types=1);

namespace Shardevcom\Core\Lifecycle;

use Throwable;

/**
 * Default LifecycleHooks: composes independent concerns (audit, events,
 * metrics) without any of them knowing about the others. Immutable —
 * with() returns a new chain, so a chain can be shared safely as a singleton.
 */
final class HookChain implements LifecycleHooks
{
    /** @var list<LifecycleHooks> */
    private array $hooks;

    public function __construct(LifecycleHooks ...$hooks)
    {
        $this->hooks = array_values($hooks);
    }

    /**
     * The chain used when a resource declares no cross-cutting concern.
     */
    public static function none(): self
    {
        return new self();
    }

    public function with(LifecycleHooks $hooks): self
    {
        return new self(...[...$this->hooks, $hooks]);
    }

    /**
     * @return list<LifecycleHooks>
     */
    public function hooks(): array
    {
        return $this->hooks;
    }

    public function beforeAction(ActionInvocation $invocation): void
    {
        foreach ($this->hooks as $hook) {
            $hook->beforeAction($invocation);
        }
    }

    public function afterAction(ActionInvocation $invocation, mixed $result): void
    {
        foreach ($this->hooks as $hook) {
            $hook->afterAction($invocation, $result);
        }
    }

    public function onActionFailure(ActionInvocation $invocation, Throwable $failure): void
    {
        foreach ($this->hooks as $hook) {
            $hook->onActionFailure($invocation, $failure);
        }
    }
}