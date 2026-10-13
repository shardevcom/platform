<?php

declare(strict_types=1);

namespace Shardevcom\Core\Authentication;

use Shardevcom\Core\Contracts\ResourceRegistry;

/**
 * T-CASA-008: enumerate a token's EFFECTIVE permissions.
 *
 * A token carries abilities as granted ('products.*', 'products.view'), which
 * is how authorization checks ask "may this context act" — but it is not the
 * same question as "what can this token actually do". Listing them is the
 * consumer-facing contract operation (a client asking scopes it was given, an
 * admin inspecting a session): granted abilities are expanded into the
 * enumerated action list so the answer contains no wildcards.
 *
 * Pure input/output on purpose — no framework, no HTTP. `ResourceRegistry` is
 * the boundary the expansion reads the action vocabulary from.
 */
final class EffectivePermissions
{
    /**
     * @param list<string> $granted abilities exactly as carried by the token
     *
     * @return list<string> enumerated permissions, unique and sorted:
     *                      'products.*' becomes every 'products.{action}'; a
     *                      bare '*' becomes every action of every registered
     *                      resource. Abilities that name an unregistered
     *                      resource stay verbatim so nothing granted is lost.
     */
    public static function from(array $granted, ResourceRegistry $registry): array
    {
        $enumerated = [];
        $verbatim = [];

        foreach ($granted as $ability) {
            if ($ability === '*') {
                foreach ($registry->all() as $name => $definition) {
                    foreach ($definition->actions() as $action) {
                        $enumerated[] = $name.'.'.$action;
                    }
                }

                continue;
            }

            if (str_ends_with($ability, '.*')) {
                $resource = substr($ability, 0, -2);

                if ($resource !== '' && $registry->has($resource)) {
                    $definition = $registry->get($resource);

                    foreach ($definition->actions() as $action) {
                        $enumerated[] = $resource.'.'.$action;
                    }

                    continue;
                }

                $verbatim[] = $ability;

                continue;
            }

            $verbatim[] = $ability;
        }

        $effective = array_values(array_unique(array_merge($enumerated, $verbatim)));

        sort($effective, SORT_STRING);

        return array_values($effective);
    }
}