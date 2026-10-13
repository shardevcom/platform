<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Exception;

use RuntimeException;

/**
 * Boundary: a command failed for an environmental reason (composer, network,
 * filesystem) rather than a usage mistake. The router reports it without
 * usage and keeps the non-zero exit code.
 */
final class CommandFailed extends RuntimeException
{
    public function __construct(string $message, private readonly int $exitCode = 1)
    {
        parent::__construct($message);
    }

    public static function create(string $message, int $exitCode = 1): self
    {
        return new self($message, $exitCode);
    }

    public function exitCode(): int
    {
        return $this->exitCode;
    }
}