<?php

declare(strict_types=1);

namespace Shardevcom\Core\Exceptions;

/**
 * State conflict (duplicate key, stale version, illegal transition).
 * Adapters map to 409.
 */
final class Conflict extends DomainException
{
    public function errorCode(): string
    {
        return 'CONFLICT';
    }
}
