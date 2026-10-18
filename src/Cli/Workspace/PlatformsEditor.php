<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace;

use RuntimeException;

/**
 * Inserts a platform entry into the API's config/platforms.php.
 *
 * The config is PHP source (it calls env() and uses ::class), so it is parsed
 * with a small bracket-matching scanner that is aware of strings and comments
 * instead of being require()d.
 */
final class PlatformsEditor
{
    public function add(string $config, GuardDefinition $platform): string
    {
        $end = $this->platformsArrayEnd($config);

        if ($end === null) {
            throw new RuntimeException("No se encontró el array 'platforms' en config/platforms.php.");
        }

        $lineStart = strrpos(substr($config, 0, $end), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;

        $block = $platform->toPhpBlock('        ')."\n\n";

        return substr($config, 0, $lineStart).$block.substr($config, $lineStart);
    }

    public function has(string $config, string $key): bool
    {
        return str_contains($config, "'{$key}' => [");
    }

    /**
     * Index of the `]` that closes the `'platforms' => [` array.
     */
    private function platformsArrayEnd(string $config): ?int
    {
        $key = strpos($config, "'platforms'");

        if ($key === false) {
            $key = strpos($config, '"platforms"');
        }

        if ($key === false) {
            return null;
        }

        $open = strpos($config, '[', $key);

        if ($open === false) {
            return null;
        }

        $length = strlen($config);
        $depth = 0;
        $single = false;
        $double = false;
        $block = false;
        $line = false;

        for ($i = $open; $i < $length; $i++) {
            $char = $config[$i];
            $next = $i + 1 < $length ? $config[$i + 1] : '';

            if ($block) {
                if ($char === '*' && $next === '/') {
                    $block = false;
                    $i++;
                }

                continue;
            }

            if ($line) {
                if ($char === "\n") {
                    $line = false;
                }

                continue;
            }

            if ($single) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === "'") {
                    $single = false;
                }

                continue;
            }

            if ($double) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === '"') {
                    $double = false;
                }

                continue;
            }

            if ($char === '/' && $next === '*') {
                $block = true;
                $i++;

                continue;
            }

            if ($char === '/' && $next === '/') {
                $line = true;
                $i++;

                continue;
            }

            if ($char === "'") {
                $single = true;

                continue;
            }

            if ($char === '"') {
                $double = true;

                continue;
            }

            if ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }
}
