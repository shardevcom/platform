<?php

declare(strict_types=1);

namespace Shardevcom\Core;

/**
 * Canonical resource operations (T-012).
 *
 * The five cases cover the platform baseline. Modules add their own
 * operations WITHOUT editing Core by passing a plain string segment
 * (e.g. 'publish') wherever a ResourceAction|string is accepted:
 * permission derivation and segment validation live here, so custom
 * actions behave exactly like canonical ones.
 */
enum ResourceAction: string
{
    case VIEW = 'view';
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case RESTORE = 'restore';

    /**
     * Normalize a canonical or custom action to its permission segment.
     *
     * Custom segments must match ^[a-z][a-z0-9_-]{0,63}$ so that
     * "{resource}.{action}" stays unambiguous (no dots, no spaces).
     */
    public static function segment(self|string $action): string
    {
        if ($action instanceof self) {
            return $action->value;
        }

        if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $action) !== 1) {
            throw new \InvalidArgumentException(
                "Invalid custom resource action [{$action}]. Use lowercase alphanumeric, dash or underscore."
            );
        }

        return $action;
    }

    public static function permission(string $resource, self|string $action): string
    {
        return $resource.'.'.self::segment($action);
    }
}
