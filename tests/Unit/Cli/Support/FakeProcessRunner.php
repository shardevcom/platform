<?php

declare(strict_types=1);

namespace Tests\Unit\Cli\Support;

use Shardevcom\Cli\Process\ProcessResult;
use Shardevcom\Cli\Process\ProcessRunner;

/**
 * Records every command instead of running it, so a workflow can be asserted
 * on the exact process list it would have executed.
 */
final class FakeProcessRunner implements ProcessRunner
{
    /** @var list<array{command: string, cwd: string|null}> */
    private array $calls = [];

    /** @var array<string, list<callable>> */
    private array $effects = [];

    /**
     * @param list<array{needle: string, exitCode?: int, output?: string}> $responses
     */
    public function __construct(private array $responses = [])
    {
    }

    /**
     * Registers a side effect triggered when a matching command runs, so a fake
     * shell can materialize what a real one would create (e.g. `create-project`).
     *
     * @param callable(): void $effect
     */
    public function after(string $needle, callable $effect): self
    {
        $this->effects[$needle][] = $effect;

        return $this;
    }

    public function run(string $command, ?string $workingDirectory = null, array $env = []): ProcessResult
    {
        $this->calls[] = ['command' => $command, 'cwd' => $workingDirectory];

        foreach ($this->effects as $needle => $effects) {
            if (str_contains($command, $needle)) {
                foreach ($effects as $effect) {
                    $effect();
                }
            }
        }

        foreach ($this->responses as $response) {
            if (str_contains($command, $response['needle'])) {
                return new ProcessResult(
                    $response['exitCode'] ?? 0,
                    $response['output'] ?? '',
                    $command
                );
            }
        }

        return ProcessResult::success('', $command);
    }

    /**
     * @return list<string>
     */
    public function commands(): array
    {
        return array_map(static fn (array $call): string => $call['command'], $this->calls);
    }

    public function ran(string $needle): bool
    {
        foreach ($this->commands() as $command) {
            if (str_contains($command, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function ranIn(string $needle, string $workingDirectory): bool
    {
        foreach ($this->calls as $call) {
            if (str_contains($call['command'], $needle) && $call['cwd'] === $workingDirectory) {
                return true;
            }
        }

        return false;
    }
}