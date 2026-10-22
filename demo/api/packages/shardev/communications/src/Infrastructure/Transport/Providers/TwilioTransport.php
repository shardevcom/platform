<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Infrastructure\Transport\Providers;

use Illuminate\Support\Facades\Http;
use Shardevcom\Communications\Contracts\ChannelTransport;
use Shardevcom\Communications\Domain\Data\DeliveryResult;
use Shardevcom\Communications\Domain\Data\OutboundMessage;
use Shardevcom\Communications\Domain\Enums\Channel;

/**
 * Twilio Programmable Messaging adapter for SMS and WhatsApp.
 *
 * Settings: account_sid, auth_token, from (SMS) / whatsapp_from (WhatsApp),
 * url (optional override) and timeout.
 */
final class TwilioTransport implements ChannelTransport
{
    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(
        private readonly Channel $channel,
        private readonly array $settings,
    ) {
    }

    public function send(OutboundMessage $message): DeliveryResult
    {
        $sid = $this->settings['account_sid'] ?? null;
        $token = $this->settings['auth_token'] ?? null;
        $from = $this->channel === Channel::WHATSAPP
            ? ($this->settings['whatsapp_from'] ?? null)
            : ($this->settings['from'] ?? null);

        if (! is_string($sid) || $sid === '' || ! is_string($token) || $token === '' || ! is_string($from) || $from === '') {
            return DeliveryResult::failed('twilio_not_configured');
        }

        $address = (string) $message->address;
        $to = $address;
        $sender = $from;

        if ($this->channel === Channel::WHATSAPP) {
            $to = str_starts_with($to, 'whatsapp:') ? $to : 'whatsapp:'.$to;
            $sender = str_starts_with($sender, 'whatsapp:') ? $sender : 'whatsapp:'.$sender;
        }

        $url = $this->settings['url'] ?? sprintf(
            'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json',
            rawurlencode($sid),
        );

        $response = Http::timeout((int) ($this->settings['timeout'] ?? 10))
            ->withBasicAuth($sid, $token)
            ->asForm()
            ->post((string) $url, [
                'To' => $to,
                'From' => $sender,
                'Body' => $message->body,
            ]);

        if ($response->failed()) {
            return DeliveryResult::failed('twilio_'.$response->status());
        }

        $providerId = $response->json('sid');

        return DeliveryResult::sent('twilio', is_scalar($providerId) ? (string) $providerId : null);
    }
}
