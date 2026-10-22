<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Infrastructure\Transport\Providers;

use Illuminate\Support\Facades\Http;
use Shardevcom\Communications\Contracts\ChannelTransport;
use Shardevcom\Communications\Domain\Data\DeliveryResult;
use Shardevcom\Communications\Domain\Data\OutboundMessage;

/**
 * Meta WhatsApp Cloud API adapter.
 *
 * Settings: phone_number_id, token, version (default v20.0), timeout.
 * The message body is sent as a text message. For approved templates, put the
 * provider template payload in metadata and use the generic http transport
 * until a template-specific adapter is needed.
 */
final class MetaWhatsAppTransport implements ChannelTransport
{
    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(private readonly array $settings)
    {
    }

    public function send(OutboundMessage $message): DeliveryResult
    {
        $phoneNumberId = $this->settings['phone_number_id'] ?? null;
        $token = $this->settings['token'] ?? null;

        if (! is_string($phoneNumberId) || $phoneNumberId === '' || ! is_string($token) || $token === '') {
            return DeliveryResult::failed('meta_whatsapp_not_configured');
        }

        $version = is_string($this->settings['version'] ?? null)
            ? $this->settings['version']
            : 'v20.0';

        $url = $this->settings['url'] ?? sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            $version,
            rawurlencode($phoneNumberId),
        );

        $response = Http::timeout((int) ($this->settings['timeout'] ?? 10))
            ->withToken($token)
            ->acceptJson()
            ->post((string) $url, [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $message->address,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $message->body,
                ],
            ]);

        if ($response->failed()) {
            return DeliveryResult::failed('meta_whatsapp_'.$response->status());
        }

        $providerId = $response->json('messages.0.id');

        return DeliveryResult::sent('meta_whatsapp', is_scalar($providerId) ? (string) $providerId : null);
    }
}
