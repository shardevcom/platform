<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Input;

use Shardevcom\Cli\Exception\InvalidInput;

/**
 * Boundary: turns raw tokens into an Input, or fails loudly.
 *
 * Supported forms: positionals (`<target>`), valued options
 * (`--laravel=12` or `--laravel 12`), boolean flags (`--dry-run`) and
 * shortcuts (`-n`). Anything the definition does not declare raises
 * InvalidInput instead of being silently ignored.
 */
final class InputParser
{
    /**
     * @param list<string> $tokens tokens WITHOUT the command name
     */
    public function parse(array $tokens, InputDefinition $definition): Input
    {
        $positional = [];
        $arguments = [];
        $options = [];

        foreach ($definition->options() as $name => $option) {
            if ($option['default'] !== null) {
                $options[$name] = $option['default'];
            }
        }

        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if ($token === '--') {
                $positional = array_merge($positional, array_slice($tokens, $index + 1));
                break;
            }

            if (str_starts_with($token, '--')) {
                $index = $this->parseLongOption($token, $tokens, $index, $definition, $options);

                continue;
            }

            if (strlen($token) > 1 && str_starts_with($token, '-')) {
                $index = $this->parseShortcut($token, $tokens, $index, $definition, $options);

                continue;
            }

            $positional[] = $token;
        }

        $this->bindPositional($positional, $definition, $arguments);

        return new Input($arguments, $options, $tokens);
    }

    /**
     * @param list<string>            $tokens
     * @param array<string, string>   $options
     */
    private function parseLongOption(string $token, array $tokens, int $index, InputDefinition $definition, array &$options): int
    {
        $body = substr($token, 2);
        $value = null;

        if (str_contains($body, '=')) {
            [$body, $value] = explode('=', $body, 2);
        }

        if (! $definition->hasOption($body)) {
            throw InvalidInput::unknownOption('--'.$body, $definition);
        }

        if (! $definition->optionTakesValue($body)) {
            if ($value !== null) {
                throw InvalidInput::unexpectedOptionValue('--'.$body);
            }

            $options[$body] = 'true';

            return $index;
        }

        if ($value === null) {
            $value = $tokens[$index + 1] ?? null;

            if ($value === null || ($value !== '' && str_starts_with($value, '-') && ! is_numeric($value))) {
                throw InvalidInput::missingOptionValue('--'.$body);
            }

            $index++;
        }

        $options[$body] = $value;

        return $index;
    }

    /**
     * @param list<string>             $tokens
     * @param array<string, string>    $options
     */
    private function parseShortcut(string $token, array $tokens, int $index, InputDefinition $definition, array &$options): int
    {
        $shortcut = substr($token, 1);
        $name = $definition->optionNameForShortcut($shortcut);

        if ($name === null) {
            throw InvalidInput::unknownOption('-'.$shortcut, $definition);
        }

        if (! $definition->optionTakesValue($name)) {
            $options[$name] = 'true';

            return $index;
        }

        $value = $tokens[$index + 1] ?? null;

        if ($value === null || (str_starts_with($value, '-') && ! is_numeric($value))) {
            throw InvalidInput::missingOptionValue('-'.$shortcut);
        }

        $options[$name] = $value;

        return $index + 1;
    }

    /**
     * @param list<string>              $positional
     * @param array<string, string>     $arguments
     */
    private function bindPositional(array $positional, InputDefinition $definition, array &$arguments): void
    {
        $names = array_keys($definition->arguments());

        if (count($positional) > count($names)) {
            throw InvalidInput::tooManyArguments($definition);
        }

        foreach ($positional as $index => $value) {
            $arguments[$names[$index]] = $value;
        }
    }
}