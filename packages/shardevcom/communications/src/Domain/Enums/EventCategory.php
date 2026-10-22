<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Domain\Enums;

enum EventCategory: string
{
    case TRANSACTIONAL = 'transactional';
    case ADMIN = 'admin';

    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }
}
