<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Infrastructure\Transport;

use Illuminate\Support\Facades\Http;
use Shardevcom\Communications\Contracts\ChannelTransport;
use Shardevcom\Communications\Domain\Data\DeliveryResult;
use Shardevcom\Communications\Domain\Data\OutboundMessage;
use Shardevcom\Communications\Domain\Enums\Channel;

/**
 * Generic JSON adapter for external SMS / WhatsApp / push providers.
 *
 * The provider URL receives:
 * {to, subject, body, metadata, event, channel}
 * plus any static `extra` payload from configuration. This keeps the module
 * provider-agnostic while still supporting a real integration.
 */
final class HttpTransport implements ChannelTransport
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
        $url = $this->settings['url'] ?? null;

        if (! is_string($url) || $url === '') {
            return DeliveryResult::failed('transport_url_not_configured');
        }

        $payload = array_merge([
            'channel' => $this->channel->value,
            'event' => $message->eventKey,
            'to' => $message->address,
            'subject' => $message->subject,
            'body' => $message->body,
            'metadata' => $message->metadata,
        ], is_array($this->settings['extra'] ?? null) ? $this->settings['extra'] : []);

        $request = Http::timeout((int) ($this->settings['timeout'] ?? 10))->acceptJson();

        $token = $this->settings['token'] ?? null;

        if (is_string($token) && $token !== '') {
            $request = $request->withToken($token);
        }

        $response = $request->post($url, $payload);

        if ($response->failed()) {
            return DeliveryResult::failed('http_'.$response->status());
        }

        $providerId = $response->json('message_id') ?? $response->json('id');

        return DeliveryResult::sent(
            is_string($this->settings['provider'] ?? null) ? $this->settings['provider'] : $this->channel->value,
            is_scalar($providerId) ? (string) $providerId : null,
        );
    }
}
