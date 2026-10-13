<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Process;

/**
 * Boundary: immutable outcome of one external command (exit code + merged
 * output). Keeps commands free of shell quoting and lets them branch on the
 * real exit status instead of guessing from output text.
 */
final class ProcessResult
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $output,
        public readonly string $command = '',
    ) {}

    public static function success(string $output = '', string $command = ''): self
    {
        return new self(0, $output, $command);
    }

    public function failed(): bool
    {
        return $this->exitCode !== 0;
    }

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }

    public function contains(string $needle): bool
    {
        return str_contains($this->output, $needle);
    }

    public function trimmedOutput(): string
    {
        return trim($this->output);
    }
}