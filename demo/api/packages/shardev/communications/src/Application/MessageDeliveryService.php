<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Application;

use Shardevcom\Communications\Contracts\MessageLog;
use Shardevcom\Communications\Contracts\PreferenceStore;
use Shardevcom\Communications\Contracts\TransportRegistry;
use Shardevcom\Communications\Domain\Data\DeliveryResult;
use Shardevcom\Communications\Domain\Data\PendingMessage;
use Shardevcom\Communications\Domain\Data\Recipient;
use Shardevcom\Communications\Domain\Enums\MessageStatus;
use RuntimeException;
use Throwable;

/**
 * Delivers a persisted message and updates its history row.
 *
 * Used synchronously by the dispatcher and asynchronously by the queue job.
 * Preferences are checked again here: a user may opt out between enqueueing
 * and the worker actually running.
 */
final class MessageDeliveryService
{
    public function __construct(
        private readonly MessageLog $log,
        private readonly PreferenceStore $preferences,
        private readonly TransportRegistry $transports,
    ) {
    }

    public function deliver(int $messageId, bool $throwOnFailure = false): DeliveryResult
    {
        $message = $this->log->find($messageId);

        if ($message === null) {
            return DeliveryResult::failed('message_not_found');
        }

        if ($this->isUnsubscribed($message)) {
            $this->log->markSkipped($messageId, MessageStatus::SKIPPED_UNSUBSCRIBED, 'unsubscribed');

            return DeliveryResult::skipped(MessageStatus::SKIPPED_UNSUBSCRIBED, 'unsubscribed');
        }

        $transport = $this->transports->for($message->channel);

        if ($transport === null) {
            return $this->fail($messageId, 'transport_not_configured', $throwOnFailure);
        }

        try {
            $result = $transport->send($message->toOutboundMessage());
        } catch (Throwable $exception) {
            return $this->fail($messageId, $exception::class.': '.$exception->getMessage(), $throwOnFailure);
        }

        if ($result->status === MessageStatus::SENT) {
            $this->log->markSent($messageId, $result->provider, $result->providerMessageId);

            return $result;
        }

        if ($result->status === MessageStatus::FAILED) {
            return $this->fail($messageId, $result->error ?? 'delivery_failed', $throwOnFailure);
        }

        $reason = $result->error ?? $result->status->value;
        $this->log->markSkipped($messageId, $result->status, $reason);

        return $result;
    }

    private function fail(int $messageId, string $error, bool $throw): DeliveryResult
    {
        $this->log->markFailed($messageId, $error);

        if ($throw) {
            throw new RuntimeException($error);
        }

        return DeliveryResult::failed($error);
    }

    private function isUnsubscribed(PendingMessage $message): bool
    {
        if ($message->recipientType !== Recipient::TYPE_USER) {
            return false;
        }

        if ($message->recipientId === null || $message->recipientGuard === null) {
            return false;
        }

        return $this->preferences->isOptedOut(
            $message->recipientGuard,
            $message->recipientId,
            $message->channel,
            $message->eventKey,
        );
    }
}
