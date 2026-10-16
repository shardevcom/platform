<?php

declare(strict_types=1);

namespace Shardevcom\Core\Exceptions;

/**
 * Caller identity is missing or invalid (expired/revoked token).
 * Adapters map to 401 / UNAUTHENTICATED semantics.
 */
final class Unauthorized extends DomainException
{
    public function errorCode(): string
    {
        return 'UNAUTHORIZED';
    }
}
