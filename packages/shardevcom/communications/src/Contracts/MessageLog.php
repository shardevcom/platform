<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Contracts;

use Shardevcom\Communications\Domain\Data\MessageDraft;
use Shardevcom\Communications\Domain\Data\PendingMessage;
use Shardevcom\Communications\Domain\Enums\MessageStatus;

/**
 * Traceability boundary for the delivery history.
 */
interface MessageLog
{
    /**
     * Persists a fully resolved message as QUEUED and returns its id.
     */
    public function record(MessageDraft $draft): int;

    public function find(int $messageId): ?PendingMessage;

    public function markSent(int $messageId, ?string $provider, ?string $providerMessageId = null): void;

    public function markFailed(int $messageId, string $error): void;

    public function markSkipped(int $messageId, MessageStatus $status, string $reason): void;

    public function markDelivered(int $messageId): void;

    public function markRead(int $messageId): void;

    /**
     * Applies a provider webhook to the row identified by its provider id.
     * Returns false when no row matches.
     */
    public function markProviderStatus(
        string $provider,
        string $providerMessageId,
        MessageStatus $status,
        ?string $error = null,
    ): bool;
}
