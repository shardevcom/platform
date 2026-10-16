<?php

declare(strict_types=1);

namespace Shardevcom\Security\Authentication;

use Shardevcom\Core\Contracts\ResourceRegistry;

/**
 * Every permission string a personal access token is allowed to carry.
 *
 * Passport is not a permission system: it only knows scopes, and it treats a
 * scope it has not been told about as a malformed request. The vocabulary it
 * has to know is therefore exactly the platform's own permission vocabulary,
 * so it is derived from the resource registry instead of being maintained
 * beside it — two lists would drift, and the failure mode of drift is a token
 * minted without the ability it was meant to have.
 *
 * The same catalogue feeds both ends of the token lifecycle: the app declares
 * it via `Passport::tokensCan()` (registration) and the login adapter mints
 * first-party tokens with `abilities()` (issuance), so a new action on a
 * resource becomes issuable without a second edit.
 */
final class ScopeCatalogue
{
    /**
     * @return array<string, string> scope id => description
     */
    public function scopes(ResourceRegistry $registry): array
    {
        $scopes = [];

        foreach ($registry->all() as $name => $definition) {
            // Both spellings are real: 'products.*' is what a human asks for
            // when scoping a token to a resource, and the enumerated actions
            // are what a narrow integration asks for. Registering only one
            // makes createToken() throw for the other.
            $scopes[$name.'.*'] = "Every permission on {$name}";

            foreach ($definition->actions() as $action) {
                $scopes[$name.'.'.$action] = "{$name}.{$action}";
            }
        }

        // Permissions that belong to no resource still ride on the token.
        // ConfigurationVisibility::ADMIN is the only visibility expressed as a
        // permission and it has no registry entry to be derived from.
        $scopes['configuration.admin'] = 'Read admin-only configuration';

        return $scopes;
    }

    /**
     * The scope ids, in the shape Passport's createToken() expects.
     *
     * @return list<string>
     */
    public function abilities(ResourceRegistry $registry): array
    {
        return array_keys($this->scopes($registry));
    }
}
