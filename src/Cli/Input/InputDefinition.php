<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Input;

/**
 * Boundary: the shape a command accepts (positionals + flags + options).
 *
 * The definition is data, not behaviour: a command declares it, the parser
 * validates against it and the help text is derived from it, so usage can
 * never drift from what the parser actually accepts.
 */
final class InputDefinition
{
    /**
     * @param array<string, string> $arguments ordered positional arguments: name => description
     * @param array<string, array{description: string, value: bool, shortcut?: string, default?: string}> $options
     */
    public function __construct(
        private array $arguments = [],
        private array $options = [],
    ) {}

    /**
     * @param array<string, string> $arguments
     * @param array<string, array{description: string, value?: bool, shortcut?: string, default?: string}> $options
     */
    public static function create(array $arguments = [], array $options = []): self
    {
        $normalized = [];

        foreach ($options as $name => $option) {
            $normalized[$name] = [
                'description' => $option['description'] ?? '',
                'value' => $option['value'] ?? false,
                'shortcut' => $option['shortcut'] ?? null,
                'default' => $option['default'] ?? null,
            ];
        }

        return new self($arguments, $normalized);
    }

    /**
     * @return array<string, string>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return array<string, array{description: string, value: bool, shortcut: string|null, default: string|null}>
     */
    public function options(): array
    {
        return $this->options;
    }

    public function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    public function optionTakesValue(string $name): bool
    {
        return $this->options[$name]['value'] ?? false;
    }

    public function optionDefault(string $name): ?string
    {
        return $this->options[$name]['default'] ?? null;
    }

    public function optionNameForShortcut(string $shortcut): ?string
    {
        foreach ($this->options as $name => $option) {
            if (($option['shortcut'] ?? null) === $shortcut) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Usage line built from the definition itself: `platform new <target> [--laravel=12]`.
     */
    public function usage(string $command): string
    {
        $usage = $command;

        foreach ($this->arguments as $name => $description) {
            $usage .= ' <'.$name.'>';
        }

        foreach ($this->options as $name => $option) {
            $usage .= $option['value'] ? ' [--'.$name.'=<value>]' : ' [--'.$name.']';
        }

        return $usage;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public function helpRows(): array
    {
        $rows = [];

        foreach ($this->arguments as $name => $description) {
            $rows[] = ['<'.$name.'>', $description];
        }

        foreach ($this->options as $name => $option) {
            $label = $option['value'] ? '--'.$name.'=<value>' : '--'.$name;

            if (is_string($option['shortcut']) && $option['shortcut'] !== '') {
                $label = $option['shortcut'].', '.$label;
            }

            if ($option['default'] !== null && $option['default'] !== '') {
                $label .= ' ('.$option['default'].')';
            }

            $rows[] = [$label, $option['description']];
        }

        return $rows;
    }
}