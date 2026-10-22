<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Rest\Http\Webhooks;

/**
 * Maps provider-specific callback payloads to the canonical webhook body:
 * {"message_id": "...", "status": "...", "error": null}.
 */
final class ProviderWebhookNormalizer
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function normalize(string $provider, array $payload): array
    {
        return match (strtolower($provider)) {
            'twilio', 'twilio_sms', 'twilio_whatsapp' => [
                'message_id' => $payload['MessageSid'] ?? $payload['SmsSid'] ?? $payload['message_id'] ?? $payload['id'] ?? null,
                'status' => $payload['MessageStatus'] ?? $payload['SmsStatus'] ?? $payload['status'] ?? null,
                'error' => $payload['ErrorMessage'] ?? $payload['error'] ?? null,
            ],
            'meta', 'meta_whatsapp', 'whatsapp' => $this->meta($payload),
            default => $payload,
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function meta(array $payload): array
    {
        $status = $payload['entry'][0]['changes'][0]['value']['statuses'][0] ?? null;

        if (! is_array($status)) {
            return $payload;
        }

        $error = $status['errors'][0] ?? null;

        return [
            'message_id' => $status['id'] ?? null,
            'status' => $status['status'] ?? null,
            'error' => is_array($error) ? ($error['title'] ?? $error['message'] ?? null) : null,
        ];
    }
}
