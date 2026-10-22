<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Domain\Enums;

/**
 * Final (or in-flight) state of a message in the delivery log.
 */
enum MessageStatus: string
{
    case QUEUED = 'queued';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case READ = 'read';
    case FAILED = 'failed';
    case SKIPPED_UNSUBSCRIBED = 'omitted_by_unsubscribe';
    case SKIPPED_MISSING_RECIPIENT = 'omitted_by_missing_recipient';

    public function isFinal(): bool
    {
        return $this !== self::QUEUED;
    }

    public function isSkipped(): bool
    {
        return $this === self::SKIPPED_UNSUBSCRIBED
            || $this === self::SKIPPED_MISSING_RECIPIENT;
    }

    public function isSuccessful(): bool
    {
        return in_array($this, [self::SENT, self::DELIVERED, self::READ], true);
    }
}
