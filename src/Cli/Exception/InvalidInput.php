<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Exception;

use RuntimeException;
use Shardevcom\Cli\Input\InputDefinition;

/**
 * Boundary: a user-facing CLI error (bad flag, missing argument).
 *
 * Carries its own exit code and prints usage, so the router can report it
 * without knowing anything about the command that failed.
 */
final class InvalidInput extends RuntimeException
{
    private function __construct(
        string $message,
        private readonly ?InputDefinition $definition = null,
        private readonly int $exitCode = 1,
    ) {
        parent::__construct($message);
    }

    public static function unknownOption(string $option, InputDefinition $definition): self
    {
        return new self("Unknown option '{$option}'.", $definition);
    }

    public static function missingOptionValue(string $option): self
    {
        return new self("Option '{$option}' requires a value.");
    }

    public static function unexpectedOptionValue(string $option): self
    {
        return new self("Option '{$option}' does not take a value.");
    }

    public static function tooManyArguments(InputDefinition $definition): self
    {
        return new self('Too many arguments.', $definition);
    }

    public static function missingArgument(string $name, InputDefinition $definition): self
    {
        return new self("Missing required argument <{$name}>.", $definition);
    }

    public static function create(string $message, int $exitCode = 1): self
    {
        return new self($message, null, $exitCode);
    }

    public function definition(): ?InputDefinition
    {
        return $this->definition;
    }

    public function exitCode(): int
    {
        return $this->exitCode;
    }
}