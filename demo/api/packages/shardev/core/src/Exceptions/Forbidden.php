<?php

declare(strict_types=1);

namespace Shardevcom\Core\Exceptions;

/**
 * Identity is valid but the AuthorizationManager denied the action.
 * Thrown BEFORE persistence is ever touched. Adapters map to 403.
 */
final class Forbidden extends DomainException
{
    public function errorCode(): string
    {
        return 'FORBIDDEN';
    }
}
