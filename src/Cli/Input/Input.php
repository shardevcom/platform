<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Input;

/**
 * Boundary: the immutable argv payload handed to a command.
 *
 * Commands read typed values from here (`input->argument('target')`,
 * `input->option('laravel')`, `input->flag('dry-run')`) instead of poking at
 * $argv, which is what makes each command unit-testable with plain arrays.
 */
final class Input
{
    /**
     * @param array<string, string> $arguments positional arguments keyed by declared name, in order
     * @param array<string, string> $options   resolved options; boolean flags are stored as 'true'
     * @param list<string>          $tokens    original tokens, kept for help/debug output
     */
    public function __construct(
        private array $arguments = [],
        private array $options = [],
        private array $tokens = [],
    ) {}

    public function argument(string $name): ?string
    {
        return $this->arguments[$name] ?? null;
    }

    public function requireArgument(string $name): string
    {
        return $this->arguments[$name] ?? throw new \InvalidArgumentException("Missing required argument <{$name}>");
    }

    public function option(string $name, ?string $default = null): ?string
    {
        return $this->options[$name] ?? $default;
    }

    public function flag(string $name): bool
    {
        return ($this->options[$name] ?? null) === 'true';
    }

    public function hasOption(string $name): bool
    {
        return array_key_exists($name, $this->options);
    }

    /**
     * @return array<string, string>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        return $this->options;
    }

    /**
     * @return list<string>
     */
    public function tokens(): array
    {
        return $this->tokens;
    }
}