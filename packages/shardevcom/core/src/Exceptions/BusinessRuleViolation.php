<?php

declare(strict_types=1);

namespace Shardevcom\Core\Exceptions;

/**
 * A domain invariant rejected the operation (beyond input shape).
 * Adapters map to 422.
 */
final class BusinessRuleViolation extends DomainException
{
    public function errorCode(): string
    {
        return 'BUSINESS_RULE_VIOLATION';
    }
}
