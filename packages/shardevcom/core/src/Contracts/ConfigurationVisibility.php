<?php

declare(strict_types=1);

namespace Shardevcom\Core\Contracts;

/**
 * Who may read a configuration provider (T-080).
 *
 * Lives in Core next to ConfigurationProvider because the contract itself
 * speaks it: a feature package cannot define a word the boundary uses without
 * Core depending on that package.
 *
 * public        - no authentication required
 * authenticated - a valid Bearer token required
 * admin         - requires 'configuration.admin' permission
 */
enum ConfigurationVisibility: string
{
    case PUBLIC = 'public';
    case AUTHENTICATED = 'authenticated';
    case ADMIN = 'admin';

    public function allows(\Shardevcom\Core\ApplicationContext $context): bool
    {
        return match ($this) {
            self::PUBLIC => true,
            self::AUTHENTICATED => $context->userId() !== null,
            self::ADMIN => $context->hasPermission('configuration.admin'),
        };
    }
}