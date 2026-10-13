<?php

declare(strict_types=1);

namespace Tests\Unit\Lifecycle;

use Shardevcom\Authorization\AuthorizationManager;
use Shardevcom\Authorization\InMemoryPermissionResolver;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\Contracts\AuthorizationManager as ManagerContract;
use Shardevcom\Core\Contracts\PermissionResolver;
use Shardevcom\Core\Exceptions\DomainException;
use Shardevcom\Core\Exceptions\Forbidden;
use Shardevcom\Core\Exceptions\ValidationFailed;
use Shardevcom\Core\Lifecycle\ActionInvocation;
use Shardevcom\Core\Lifecycle\ActionLifecycle;
use Shardevcom\Core\Lifecycle\HookChain;
use Shardevcom\Core\Lifecycle\LifecycleHooks;
use Shardevcom\Core\ResourceAction;
use PHPUnit\Framework\TestCase;

/**
 * T-050 acceptance: order before → authorize → operation → after, failure
 * reporting with the original exception rethrown, and no way to reach the
 * operation outside execute().
 */
final class ActionLifecycleTest extends TestCase
{
    public function test_order_is_before_authorize_operation_after(): void
    {
        $trace = new \ArrayObject();
        $lifecycle = $this->lifecycle($trace, ['products.update']);

        $result = $lifecycle->execute(
            'products',
            ResourceAction::UPDATE,
            ApplicationContext::create(['userId' => 'u-1']),
            function (ApplicationContext $context) use ($trace): string {
                $trace[] = 'operation:'.$context->userId();

                return 'updated';
            },
        );

        $this->assertSame('updated', $result);
        $this->assertSame(
            ['hook:before', 'authorize', 'operation:u-1', 'hook:after'],
            $trace->getArrayCopy(),
        );
    }

    public function test_denied_action_reports_failure_and_rethrows_the_original(): void
    {
        $trace = new \ArrayObject();
        $lifecycle = $this->lifecycle($trace, []);

        $caught = null;

        try {
            $lifecycle->execute('products', ResourceAction::DELETE, ApplicationContext::create(), function () use ($trace): void {
                $trace[] = 'operation';
            });
            $this->fail('Forbidden was not thrown.');
        } catch (Forbidden $e) {
            $caught = $e;
        }

        $this->assertSame(['hook:before', 'authorize', 'failure:FORBIDDEN'], $trace->getArrayCopy());
        $this->assertSame('FORBIDDEN', $caught->errorCode());
        $this->assertSame('products', $caught->context()['resource']);
    }

    public function test_validation_failure_inside_the_operation_is_reported_and_rethrown_unchanged(): void
    {
        $trace = new \ArrayObject();
        $lifecycle = $this->lifecycle($trace, ['products.create']);
        $failure = new ValidationFailed(['sku' => 'required']);

        $caught = null;

        try {
            $lifecycle->execute('products', ResourceAction::CREATE, ApplicationContext::create(), function () use ($failure, $trace): void {
                $trace[] = 'operation';

                throw $failure;
            });
        } catch (ValidationFailed $e) {
            $caught = $e;
        }

        $this->assertSame($failure, $caught, 'the domain error must not be wrapped');
        $this->assertSame(['sku' => 'required'], $caught->errors());
        $this->assertSame(
            ['hook:before', 'authorize', 'operation', 'failure:VALIDATION_FAILED'],
            $trace->getArrayCopy(),
        );
    }

    public function test_non_domain_failures_are_also_reported_and_rethrown_unchanged(): void
    {
        $trace = new \ArrayObject();
        $lifecycle = $this->lifecycle($trace, ['products.view']);
        $failure = new \RuntimeException('database is down');

        try {
            $lifecycle->execute('products', ResourceAction::VIEW, ApplicationContext::create(), function () use ($failure, $trace): void {
                $trace[] = 'operation';

                throw $failure;
            });
            $this->fail('RuntimeException was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame($failure, $e);
        }

        $this->assertSame(
            ['hook:before', 'authorize', 'operation', 'failure:database is down'],
            $trace->getArrayCopy(),
        );
    }

    public function test_hooks_receive_resource_action_context_and_attributes(): void
    {
        $trace = new \ArrayObject();
        $hooks = new class($trace) implements LifecycleHooks
        {
            public function __construct(private \ArrayObject $trace)
            {
            }

            public function beforeAction(ActionInvocation $invocation): void
            {
                $this->trace[] = 'before:'.$invocation->permission().':'.json_encode($invocation->attributes());
            }

            public function afterAction(ActionInvocation $invocation, mixed $result): void
            {
                $this->trace[] = 'after:'.$result.':'.$invocation->context()->correlationId();
            }

            public function onActionFailure(ActionInvocation $invocation, \Throwable $failure): void
            {
                $this->trace[] = 'failure';
            }
        };

        $lifecycle = new ActionLifecycle($this->tracingAuthorization($trace, ['products.publish']), $hooks);

        $lifecycle->execute(
            'products',
            'publish',
            ApplicationContext::create(['requestId' => 'req-1']),
            fn (): string => 'published',
            ['fields' => ['title']],
        );

        $this->assertSame(
            ['before:products.publish:{"fields":["title"]}', 'authorize', 'after:published:req-1'],
            $trace->getArrayCopy(),
        );
    }

    public function test_custom_string_actions_are_authorized_with_their_own_permission(): void
    {
        $asked = new \ArrayObject();
        $resolver = new class(new InMemoryPermissionResolver(['products.publish']), $asked) implements PermissionResolver
        {
            public function __construct(private InMemoryPermissionResolver $inner, private \ArrayObject $asked)
            {
            }

            public function can(ApplicationContext $context, string $resource, ResourceAction|string $action): bool
            {
                $this->asked[] = ResourceAction::permission($resource, $action);

                return $this->inner->can($context, $resource, $action);
            }
        };

        (new ActionLifecycle(new AuthorizationManager($resolver), HookChain::none()))
            ->execute('products', 'publish', ApplicationContext::create(), fn (): string => 'ok');

        $this->assertSame(['products.publish'], $asked->getArrayCopy());
    }

    public function test_hook_chain_fans_out_to_every_hook_in_order(): void
    {
        $trace = new \ArrayObject();
        $chain = HookChain::none()
            ->with($this->tracingHook($trace, 'audit'))
            ->with($this->tracingHook($trace, 'events'));

        $lifecycle = new ActionLifecycle($this->tracingAuthorization($trace, ['products.view']), $chain);

        $lifecycle->execute('products', ResourceAction::VIEW, ApplicationContext::create(), fn (): string => 'ok');

        $this->assertSame(
            [
                'audit:before', 'events:before',
                'authorize',
                'audit:after', 'events:after',
            ],
            $trace->getArrayCopy(),
        );
    }

    public function test_public_operations_route_through_execute(): void
    {
        $trace = new \ArrayObject();
        $lifecycle = $this->lifecycle($trace, ['products.create', 'products.update', 'products.delete']);
        $repository = new class($lifecycle, $trace)
        {
            public function __construct(private ActionLifecycle $lifecycle, private \ArrayObject $trace)
            {
            }

            public function create(ApplicationContext $context): string
            {
                return $this->lifecycle->execute('products', ResourceAction::CREATE, $context, function (): string {
                    $this->trace[] = 'persist:create';

                    return 'created';
                });
            }

            public function update(ApplicationContext $context): string
            {
                return $this->lifecycle->execute('products', ResourceAction::UPDATE, $context, function (): string {
                    $this->trace[] = 'persist:update';

                    return 'updated';
                });
            }

            public function delete(ApplicationContext $context): string
            {
                return $this->lifecycle->execute('products', ResourceAction::DELETE, $context, function (): string {
                    $this->trace[] = 'persist:delete';

                    return 'deleted';
                });
            }
        };

        $context = ApplicationContext::create();
        $repository->create($context);
        $repository->update($context);
        $repository->delete($context);

        $this->assertSame([
            'hook:before', 'authorize', 'persist:create', 'hook:after',
            'hook:before', 'authorize', 'persist:update', 'hook:after',
            'hook:before', 'authorize', 'persist:delete', 'hook:after',
        ], $trace->getArrayCopy());
    }

    public function test_execute_is_the_only_entry_point_so_no_operation_can_bypass_it(): void
    {
        $reflection = new \ReflectionClass(ActionLifecycle::class);

        $this->assertTrue($reflection->isFinal(), 'the lifecycle cannot be reopened by subclasses');

        $public = array_values(array_filter(
            array_map(
                static fn (\ReflectionMethod $m): string => $m->getName(),
                $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
            ),
            static fn (string $name): bool => $name !== '__construct',
        ));

        $this->assertSame(['execute'], $public, 'execute() must be the only public entry point');
    }

    public function test_lifecycle_stays_framework_agnostic(): void
    {
        $files = glob(dirname(__DIR__, 3).'/packages/shardevcom/core/src/Lifecycle/*.php');
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);

            preg_match_all('/^use\s+([^;]+);/m', $source, $imports);

            foreach ($imports[1] as $import) {
                $this->assertDoesNotMatchRegularExpression(
                    '/^(Illuminate|Database|Eloquent|GraphQL)\\\\/',
                    trim($import),
                    basename($file).' must not import framework or persistence code',
                );
            }
        }
    }

    /**
     * Lifecycle wired to a tracing hook spy and to the real authorization
     * decision, so grants/denials behave exactly like production.
     *
     * @param list<string> $grants empty list denies everything
     */
    private function lifecycle(\ArrayObject $trace, array $grants): ActionLifecycle
    {
        $hooks = new class($trace) implements LifecycleHooks
        {
            public function __construct(private \ArrayObject $trace)
            {
            }

            public function beforeAction(ActionInvocation $invocation): void
            {
                $this->trace[] = 'hook:before';
            }

            public function afterAction(ActionInvocation $invocation, mixed $result): void
            {
                $this->trace[] = 'hook:after';
            }

            public function onActionFailure(ActionInvocation $invocation, \Throwable $failure): void
            {
                $this->trace[] = 'failure:'.($failure instanceof DomainException
                    ? $failure->errorCode()
                    : $failure->getMessage());
            }
        };

        return new ActionLifecycle($this->tracingAuthorization($trace, $grants), $hooks);
    }

    /**
     * Authorization spy that records the decision point and then delegates to
     * the real AuthorizationManager, so Forbidden still carries its real context.
     *
     * @param list<string> $grants
     */
    private function tracingAuthorization(\ArrayObject $trace, array $grants = []): ManagerContract
    {
        $inner = new AuthorizationManager(new InMemoryPermissionResolver($grants));

        return new class($inner, $trace) implements ManagerContract
        {
            public function __construct(private ManagerContract $inner, private \ArrayObject $trace)
            {
            }

            public function authorize(string $resource, ResourceAction|string $action, ApplicationContext $context): void
            {
                $this->trace[] = 'authorize';

                $this->inner->authorize($resource, $action, $context);
            }
        };
    }

    private function tracingHook(\ArrayObject $trace, string $name): LifecycleHooks
    {
        return new class($trace, $name) implements LifecycleHooks
        {
            public function __construct(private \ArrayObject $trace, private string $name)
            {
            }

            public function beforeAction(ActionInvocation $invocation): void
            {
                $this->trace[] = $this->name.':before';
            }

            public function afterAction(ActionInvocation $invocation, mixed $result): void
            {
                $this->trace[] = $this->name.':after';
            }

            public function onActionFailure(ActionInvocation $invocation, \Throwable $failure): void
            {
                $this->trace[] = $this->name.':failure';
            }
        };
    }
}