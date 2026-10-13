<?php

declare(strict_types=1);

namespace Shardevcom\Widgets\Contracts;

use Shardevcom\Core\ApplicationContext;

/**
 * Visibility levels for widget providers.
 */
enum WidgetVisibility: string
{
    case PUBLIC = 'public';
    case AUTHENTICATED = 'authenticated';
    case ADMIN = 'admin';

    public function allows(ApplicationContext $context): bool
    {
        return match ($this) {
            self::PUBLIC => true,
            self::AUTHENTICATED => $context->userId() !== null,
            self::ADMIN => $context->hasPermission('widgets.admin'),
        };
    }
}