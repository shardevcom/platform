<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Rest\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shardevcom\Communications\Application\Commands\ProcessProviderWebhook;
use Shardevcom\Communications\Rest\Http\Webhooks\ProviderWebhookNormalizer;
use Shardevcom\Core\Results\ApiResponse;

final class WebhookController
{
    public function __construct(
        private readonly ProcessProviderWebhook $processWebhook,
        private readonly ProviderWebhookNormalizer $normalizer,
    ) {
    }

    public function handle(string $provider, Request $request): JsonResponse
    {
        $verification = $this->verify($provider, $request);

        if ($verification !== null) {
            return $verification;
        }

        $payload = $request->json()->all();

        if (! is_array($payload) || $payload === []) {
            $payload = $request->all();
        }

        return response()->json(ApiResponse::success(
            $this->processWebhook->execute(
                $provider,
                $this->normalizer->normalize($provider, is_array($payload) ? $payload : []),
            ),
            'Webhook processed.',
        )->toArray());
    }

    private function verify(string $provider, Request $request): ?JsonResponse
    {
        $providerSettings = config('communications.webhooks.providers.'.strtolower($provider), []);
        $driver = is_array($providerSettings) ? ($providerSettings['driver'] ?? null) : null;

        if ($driver === 'twilio') {
            $token = $providerSettings['auth_token'] ?? null;

            if (! is_string($token) || $token === '') {
                return $this->error('WEBHOOK_NOT_CONFIGURED', 'The Twilio auth token is not configured.', 503);
            }

            return $this->verifyTwilio($request, $token)
                ? null
                : $this->error('INVALID_SIGNATURE', 'The webhook signature is invalid.', 401);
        }

        $secret = (string) config('communications.webhooks.secret', '');

        if ($secret === '') {
            return $this->error('WEBHOOK_NOT_CONFIGURED', 'The webhook secret is not configured.', 503);
        }

        $signature = (string) $request->header((string) config('communications.webhooks.header', 'X-Communications-Signature'), '');
        $signature = str_starts_with($signature, 'sha256=') ? substr($signature, 7) : $signature;
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature)
            ? null
            : $this->error('INVALID_SIGNATURE', 'The webhook signature is invalid.', 401);
    }

    private function verifyTwilio(Request $request, string $token): bool
    {
        $signature = (string) $request->header('X-Twilio-Signature', '');
        $params = $request->post();
        ksort($params);

        $data = $request->fullUrl();

        foreach ($params as $key => $value) {
            if (is_scalar($value)) {
                $data .= $key.$value;
            }
        }

        return hash_equals(base64_encode(hash_hmac('sha1', $data, $token, true)), $signature);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(
            ApiResponse::error(new \Shardevcom\Core\Results\ApiError($code, $message, [], $status))->toArray(),
            $status,
        );
    }
}
