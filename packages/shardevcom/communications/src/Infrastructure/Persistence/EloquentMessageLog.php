<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Infrastructure\Persistence;

use Illuminate\Support\Str;
use Shardevcom\Communications\Contracts\MessageLog;
use Shardevcom\Communications\Domain\Data\MessageDraft;
use Shardevcom\Communications\Domain\Data\PendingMessage;
use Shardevcom\Communications\Domain\Enums\MessageStatus;

final class EloquentMessageLog implements MessageLog
{
    public function record(MessageDraft $draft): int
    {
        $message = CommunicationMessage::query()->create([
            'uuid' => (string) Str::uuid(),
            'event_key' => $draft->eventKey,
            'event_id' => $draft->eventId,
            'template_id' => $draft->templateId,
            'channel' => $draft->channel->value,
            'recipient_type' => $draft->recipientType,
            'recipient_id' => $draft->recipientId,
            'recipient_guard' => $draft->recipientGuard,
            'recipient_address' => $draft->recipientAddress,
            'locale' => $draft->locale,
            'format' => $draft->format->value,
            'status' => MessageStatus::QUEUED->value,
            'subject' => $draft->subject,
            'body' => $draft->body,
            'payload' => $draft->payload === [] ? null : $draft->payload,
            'metadata' => $draft->metadata === [] ? null : $draft->metadata,
            'attempts' => 0,
            'queued_at' => now(),
        ]);

        return (int) $message->getKey();
    }

    public function find(int $messageId): ?PendingMessage
    {
        $message = CommunicationMessage::query()->find($messageId);

        if ($message === null) {
            return null;
        }

        return new PendingMessage(
            (int) $message->getKey(),
            (string) $message->uuid,
            (string) $message->event_key,
            $message->channel,
            (string) $message->recipient_type,
            $message->recipient_id,
            $message->recipient_guard,
            $message->recipient_address,
            (string) $message->locale,
            $message->format,
            $message->subject,
            (string) $message->body,
            is_array($message->payload) ? $message->payload : [],
            is_array($message->metadata) ? $message->metadata : [],
            (int) $message->attempts,
        );
    }

    public function markSent(int $messageId, ?string $provider, ?string $providerMessageId = null): void
    {
        $message = $this->findModel($messageId);

        if ($message === null) {
            return;
        }

        $message->attempts++;
        $message->status = MessageStatus::SENT;
        $message->provider = $provider;
        $message->provider_message_id = $providerMessageId;
        $message->error = null;
        $message->sent_at = now();
        $message->save();
    }

    public function markFailed(int $messageId, string $error): void
    {
        $message = $this->findModel($messageId);

        if ($message === null) {
            return;
        }

        $message->attempts++;
        $message->status = MessageStatus::FAILED;
        $message->error = $error;
        $message->failed_at = now();
        $message->save();
    }

    public function markSkipped(int $messageId, MessageStatus $status, string $reason): void
    {
        $message = $this->findModel($messageId);

        if ($message === null) {
            return;
        }

        $message->status = $status;
        $message->error = $reason;
        $message->skipped_at = now();
        $message->save();
    }

    public function markDelivered(int $messageId): void
    {
        $message = $this->findModel($messageId);

        if ($message === null) {
            return;
        }

        $message->status = MessageStatus::DELIVERED;
        $message->delivered_at = now();
        $message->save();
    }

    public function markRead(int $messageId): void
    {
        $message = $this->findModel($messageId);

        if ($message === null) {
            return;
        }

        $message->status = MessageStatus::READ;
        $message->read_at = now();
        $message->save();
    }

    public function markProviderStatus(
        string $provider,
        string $providerMessageId,
        MessageStatus $status,
        ?string $error = null,
    ): bool {
        $message = CommunicationMessage::query()
            ->where('provider', $provider)
            ->where('provider_message_id', $providerMessageId)
            ->first();

        if ($message === null) {
            return false;
        }

        $message->status = $status;

        match ($status) {
            MessageStatus::SENT => $message->sent_at = now(),
            MessageStatus::DELIVERED => $message->delivered_at = now(),
            MessageStatus::READ => $message->read_at = now(),
            MessageStatus::FAILED => $message->failed_at = now(),
            default => null,
        };

        if ($status === MessageStatus::FAILED) {
            $message->error = $error;
        }

        $message->save();

        return true;
    }

    private function findModel(int $messageId): ?CommunicationMessage
    {
        return CommunicationMessage::query()->find($messageId);
    }
}
