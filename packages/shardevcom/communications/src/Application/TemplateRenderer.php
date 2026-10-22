<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Application;

use JsonException;
use Shardevcom\Core\Exceptions\ValidationFailed;
use Stringable;

/**
 * Replaces {{ variable }} / {{ nested.variable }} placeholders.
 *
 * HTML templates escape substituted values by default, so a name coming from
 * user input cannot inject markup into an email. The template itself is
 * trusted because it is authored by an administrator.
 */
final class TemplateRenderer
{
    public function __construct(private readonly bool $strict = false)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function render(string $template, array $payload, bool $escape = false): string
    {
        $rendered = preg_replace_callback(
            '/\{\{\s*([A-Za-z0-9_.]+)\s*\}\}/',
            function (array $matches) use ($payload, $escape): string {
                [$found, $value] = $this->resolve($matches[1], $payload);

                if (! $found) {
                    if ($this->strict) {
                        throw new ValidationFailed([
                            'template' => ["Missing template variable [{$matches[1]}]."],
                        ]);
                    }

                    return '';
                }

                $string = $this->stringify($value);

                return $escape
                    ? htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    : $string;
            },
            $template,
        );

        return $rendered ?? $template;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{bool, mixed}
     */
    private function resolve(string $path, array $payload): array
    {
        $value = $payload;

        foreach (explode('.', $path) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];

                continue;
            }

            if (is_object($value) && isset($value->{$segment})) {
                $value = $value->{$segment};

                continue;
            }

            return [false, null];
        }

        return [true, $value];
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        try {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return '';
        }
    }
}
