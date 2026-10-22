<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Infrastructure\Transport\Providers;

use Illuminate\Support\Facades\Http;
use Shardevcom\Communications\Contracts\ChannelTransport;
use Shardevcom\Communications\Domain\Data\DeliveryResult;
use Shardevcom\Communications\Domain\Data\OutboundMessage;

/**
 * Firebase Cloud Messaging HTTP v1 adapter.
 *
 * Settings: project_id, token (OAuth2 bearer), timeout.
 * The recipient address is the device FCM token. Metadata may carry `data`
 * (string map) for the client payload.
 */
final class FcmTransport implements ChannelTransport
{
    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(private readonly array $settings)
    {
    }

    public function send(OutboundMessage $message): DeliveryResult
    {
        $projectId = $this->settings['project_id'] ?? null;
        $token = $this->settings['token'] ?? null;

        if (! is_string($projectId) || $projectId === '' || ! is_string($token) || $token === '') {
            return DeliveryResult::failed('fcm_not_configured');
        }

        $url = $this->settings['url'] ?? sprintf(
            'https://fcm.googleapis.com/v1/projects/%s/messages:send',
            rawurlencode($projectId),
        );

        $data = $message->metadata['data'] ?? null;
        $payload = [
            'token' => $message->address,
            'notification' => [
                'title' => $message->subject ?? (string) config('app.name', 'Notification'),
                'body' => $message->body,
            ],
        ];

        if (is_array($data)) {
            $payload['data'] = array_map(
                static fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
                $data,
            );
        }

        $response = Http::timeout((int) ($this->settings['timeout'] ?? 10))
            ->withToken($token)
            ->acceptJson()
            ->post((string) $url, ['message' => $payload]);

        if ($response->failed()) {
            return DeliveryResult::failed('fcm_'.$response->status());
        }

        $providerId = $response->json('name');

        return DeliveryResult::sent('fcm', is_scalar($providerId) ? (string) $providerId : null);
    }
}
