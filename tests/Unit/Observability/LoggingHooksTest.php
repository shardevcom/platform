<?php

declare(strict_types=1);

namespace Tests\Unit\Observability;

use PHPUnit\Framework\TestCase;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\Contracts\Logger;
use Shardevcom\Core\Lifecycle\ActionInvocation;
use Shardevcom\Observability\Lifecycle\LoggingHooks;

/**
 * T-CASA-023: the lifecycle half of the trace carries the correlation ids the
 * request middleware assigned, on success and on failure alike.
 */
final class LoggingHooksTest extends TestCase
{
    public function test_after_action_logs_dimensions_with_correlation_id(): void
    {
        $logger = new RecordingLogger();
        $hooks = new LoggingHooks($logger);

        $hooks->beforeAction($this->invocation());
        $hooks->afterAction($this->invocation(), ['ok' => true]);

        $this->assertCount(1, $logger->info);
        $this->assertSame('11111111-1111-4111-8111-111111111111', $logger->info[0]['context']['correlation_id']);
        $this->assertSame('list', $logger->info[0]['context']['action']);
        $this->assertSame('products', $logger->info[0]['context']['resource']);
        $this->assertArrayHasKey('duration_ms', $logger->info[0]['context']);
    }

    public function test_on_failure_logs_error_with_the_same_ids(): void
    {
        $logger = new RecordingLogger();
        $hooks = new LoggingHooks($logger);

        $hooks->beforeAction($this->invocation());
        $hooks->onActionFailure($this->invocation(), new \DomainException('boom'));

        $this->assertCount(1, $logger->error);
        $this->assertSame('11111111-1111-4111-8111-111111111111', $logger->error[0]['context']['correlation_id']);
        $this->assertSame(\DomainException::class, $logger->error[0]['context']['failure']);
    }

    private function invocation(): ActionInvocation
    {
        $context = ApplicationContext::create([
            'userId' => '7',
            'tenantId' => 'A',
            'guard' => 'admin',
            'requestId' => '22222222-2222-4222-8222-222222222222',
            'correlationId' => '11111111-1111-4111-8111-111111111111',
        ]);

        return ActionInvocation::forAction('products', 'list', $context);
    }
}

final class RecordingLogger implements Logger
{
    /**
     * @var list<array{message: string, context: array<string, mixed>}>
     */
    public array $info = [];

    /**
     * @var list<array{message: string, context: array<string, mixed>}>
     */
    public array $error = [];

    public function emergency(string $message, array $context = []): void
    {
    }

    public function alert(string $message, array $context = []): void
    {
    }

    public function critical(string $message, array $context = []): void
    {
    }

    public function warning(string $message, array $context = []): void
    {
    }

    public function notice(string $message, array $context = []): void
    {
    }

    public function info(string $message, array $context = []): void
    {
        $this->info[] = ['message' => $message, 'context' => $context];
    }

    public function debug(string $message, array $context = []): void
    {
    }

    public function log(string $level, string $message, array $context = []): void
    {
    }

    public function error(string $message, array $context = []): void
    {
        $this->error[] = ['message' => $message, 'context' => $context];
    }
}