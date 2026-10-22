<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Application\Commands;

use Shardevcom\Communications\Contracts\MessageLog;
use Shardevcom\Communications\Domain\Enums\MessageStatus;
use Shardevcom\Core\Exceptions\ResourceNotFound;
use Shardevcom\Core\Exceptions\ValidationFailed;

final class ProcessProviderWebhook
{
    public function __construct(private readonly MessageLog $messages)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{provider: string, message_id: string, status: string}
     */
    public function execute(string $provider, array $payload): array
    {
        $errors = [];
        $provider = trim($provider);

        if ($provider === '') {
            $errors['provider'] = 'The provider is required.';
        }

        $providerMessageId = $payload['message_id'] ?? $payload['id'] ?? null;

        if (! is_string($providerMessageId) || $providerMessageId === '') {
            $errors['message_id'] = 'The provider message id is required.';
        }

        $rawStatus = $payload['status'] ?? null;
        $status = is_string($rawStatus) ? $this->status($rawStatus) : null;

        if ($status === null) {
            $errors['status'] = 'The status must be queued, sent, delivered, read or failed.';
        }

        $error = $payload['error'] ?? null;

        if ($error !== null && ! is_string($error)) {
            $errors['error'] = 'The error must be a string or null.';
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        $updated = $this->messages->markProviderStatus(
            $provider,
            (string) $providerMessageId,
            $status,
            is_string($error) ? $error : null,
        );

        if (! $updated) {
            throw ResourceNotFound::for('communication_messages', (string) $providerMessageId);
        }

        return [
            'provider' => $provider,
            'message_id' => (string) $providerMessageId,
            'status' => $status->value,
        ];
    }

    private function status(string $status): ?MessageStatus
    {
        return match (strtolower($status)) {
            'queued' => MessageStatus::QUEUED,
            'sending', 'sent', 'accepted' => MessageStatus::SENT,
            'delivered' => MessageStatus::DELIVERED,
            'read' => MessageStatus::READ,
            'failed', 'undelivered', 'bounced', 'rejected' => MessageStatus::FAILED,
            default => null,
        };
    }
}
