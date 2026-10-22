<?php

declare(strict_types=1);

namespace Shardevcom\Security\Authentication;

use Shardevcom\Core\Contracts\SecondFactorGate;

/**
 * Default gate used when no second-factor package is installed.
 */
final class NullSecondFactorGate implements SecondFactorGate
{
    public function challenge(object $user, string $guard): ?array
    {
        return null;
    }
}
