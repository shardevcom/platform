<?php

declare(strict_types=1);

namespace Shardevcom\Core\Exceptions;

/**
 * The request is valid in shape but the payload exceeds a declared limit
 * (file size, row count). Adapters map to 413.
 */
final class RequestEntityTooLarge extends DomainException
{
    public function errorCode(): string
    {
        return 'REQUEST_ENTITY_TOO_LARGE';
    }
}