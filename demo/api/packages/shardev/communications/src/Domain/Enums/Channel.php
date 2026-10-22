<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Domain\Enums;

/**
 * Delivery channel supported by the communications module.
 *
 * The value is persisted (event matrix, templates, preferences, message log),
 * so it is part of the module's public data contract.
 */
enum Channel: string
{
    case EMAIL = 'email';
    case SMS = 'sms';
    case WHATSAPP = 'whatsapp';
    case PUSH = 'push';
    case IN_APP = 'in_app';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $channel): string => $channel->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'Email',
            self::SMS => 'SMS',
            self::WHATSAPP => 'WhatsApp',
            self::PUSH => 'Push',
            self::IN_APP => 'In-app',
        };
    }

    /**
     * In-app messages are attached to the notifiable user, not to an address.
     */
    public function requiresAddress(): bool
    {
        return $this !== self::IN_APP;
    }
}
