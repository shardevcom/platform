<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Process;

/**
 * Boundary: the single place where the CLI is allowed to spawn processes.
 *
 * Commands depend on this contract, never on shell_exec/proc_open, so a test
 * can assert on the commands a workflow would run without running them.
 */
interface ProcessRunner
{
    /**
     * @param  array<string, string|int|bool> $env extra environment variables
     */
    public function run(string $command, ?string $workingDirectory = null, array $env = []): ProcessResult;
}