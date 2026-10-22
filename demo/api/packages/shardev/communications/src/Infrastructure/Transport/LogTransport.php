<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Infrastructure\Transport;

use Illuminate\Support\Facades\Log;
use Shardevcom\Communications\Contracts\ChannelTransport;
use Shardevcom\Communications\Domain\Data\DeliveryResult;
use Shardevcom\Communications\Domain\Data\OutboundMessage;

/**
 * Safe fallback for channels without provider credentials.
 *
 * It does not pretend a message was delivered by a provider: the history row
 * is SENT with provider=log, which is intentionally visible in the audit.
 */
final class LogTransport implements ChannelTransport
{
    public function send(OutboundMessage $message): DeliveryResult
    {
        Log::info('communication.sent', [
            'event' => $message->eventKey,
            'channel' => $message->channel->value,
            'recipient_type' => $message->recipientType,
            'recipient_id' => $message->recipientId,
            'recipient_guard' => $message->recipientGuard,
            'address' => $message->address,
            'subject' => $message->subject,
            'metadata' => $message->metadata,
        ]);

        return DeliveryResult::sent('log');
    }
}
