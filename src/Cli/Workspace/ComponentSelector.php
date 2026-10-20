<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace;

use Shardevcom\Cli\Exception\CommandFailed;

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
     * Resolves the guard a build/dev command targets.
     *
     * `--guard` must name a guard the component actually serves: otherwise the
     * driver would shell out to a non-existent app (`npm run dev:<guard>`).
     * Kept here so build and dev reject an unknown guard the same way.
     */
    public static function guard(Component $component, ?string $guard): ?string
    {
        if ($guard === null || $guard === '') {
            return null;
        }

        if ($component->serves($guard)) {
            return $guard;
        }

        $known = $component->guards === [] ? 'none' : implode(', ', $component->guardNames());

        throw CommandFailed::create(
            "Component '{$component->name}' does not serve the '{$guard}' guard (known: {$known})."
        );
    }

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
