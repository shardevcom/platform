<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Contracts;

use Shardevcom\Communications\Domain\Data\Recipient;

/**
 * Resolves the admins (or roles) that receive an internal alert.
 *
 * Implement this port to plug the platform's real role/team storage without
 * coupling the module to a particular authorization model.
 */
interface AdminRecipientResolver
{
    /**
     * An empty role list means "all configured admin recipients".
     *
     * @param list<string> $roles
     * @return list<Recipient>
     */
    public function resolve(array $roles = []): array;
}
