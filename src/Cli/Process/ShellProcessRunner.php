<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Process;

/**
 * Boundary: the only implementation that really spawns processes.
 *
 * proc_open (not shell_exec) because the exit code is part of the contract:
 * shell_exec can never report a failure, which is why the previous CLI
 * treated failed `composer`/`artisan` runs as successes.
 */
final class ShellProcessRunner implements ProcessRunner
{
    /**
     * @param array<string, string|int|bool> $env
     */
    public function run(string $command, ?string $workingDirectory = null, array $env = []): ProcessResult
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $environment = null;

        if ($env !== []) {
            $environment = array_merge(self::currentEnvironment(), array_map(
                static fn (string|int|bool $value): string => (string) $value,
                $env
            ));
        }

        $process = @proc_open($command, $descriptors, $pipes, $workingDirectory, $environment);

        if (! is_resource($process)) {
            return new ProcessResult(127, "Unable to spawn process: {$command}", $command);
        }

        fclose($pipes[0]);
        $output = (string) stream_get_contents($pipes[1]);
        $output .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return new ProcessResult(proc_close($process), $output, $command);
    }

    /**
     * @return array<string, string>
     */
    private static function currentEnvironment(): array
    {
        $environment = [];

        foreach ($_SERVER as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $environment[$key] = $value;
            }
        }

        return $environment;
    }

    /**
     * Quotes a value for safe interpolation in a shell command.
     */
    public static function quote(string $value): string
    {
        return escapeshellarg($value);
    }
}