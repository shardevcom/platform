<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace;

/**
 * Resolves which components a workspace command should act on.
 *
 * A named component wins over the default, `--only` allows a comma-separated
 * subset, and an empty selection means "every enabled component". Centralised
 * so install/build/status agree on the same rule.
 */
final class ComponentSelector
{
    /**
     * @return list<Component>
     */
    public static function resolve(Workspace $workspace, ?string $component, ?string $only = null): array
    {
        $names = [];

        if ($component !== null && $component !== '') {
            $names[] = $component;
        }

        if ($only !== null && $only !== '') {
            foreach (explode(',', $only) as $name) {
                $name = trim($name);

                if ($name !== '') {
                    $names[] = $name;
                }
            }
        }

        if ($names === []) {
            return $workspace->manifest->enabled();
        }

        return array_map(
            static fn (string $name): Component => $workspace->manifest->component($name),
            $names
        );
    }
}
