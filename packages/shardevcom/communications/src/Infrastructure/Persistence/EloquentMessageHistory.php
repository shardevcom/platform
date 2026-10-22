<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Infrastructure\Persistence;

use Shardevcom\Communications\Contracts\MessageHistory;
use Shardevcom\Communications\Domain\Data\MessageHistoryFilter;
use Shardevcom\Communications\Domain\Data\MessageHistoryPage;
use Shardevcom\Core\Results\PaginationMeta;

final class EloquentMessageHistory implements MessageHistory
{
    public function paginate(MessageHistoryFilter $filter, int $page, int $perPage): MessageHistoryPage
    {
        $query = CommunicationMessage::query();

        if ($filter->eventKey !== null) {
            $query->where('event_key', $filter->eventKey);
        }

        if ($filter->channel !== null) {
            $query->where('channel', $filter->channel->value);
        }

        if ($filter->status !== null) {
            $query->where('status', $filter->status->value);
        }

        if ($filter->recipientType !== null) {
            $query->where('recipient_type', $filter->recipientType);
        }

        if ($filter->recipientId !== null) {
            $query->where('recipient_id', $filter->recipientId);
        }

        if ($filter->recipientGuard !== null) {
            $query->where('recipient_guard', $filter->recipientGuard);
        }

        if ($filter->from !== null) {
            $query->where('created_at', '>=', $filter->from);
        }

        if ($filter->to !== null) {
            $query->where('created_at', '<=', $filter->to);
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);

        $items = [];

        foreach ($paginator->items() as $message) {
            if ($message instanceof CommunicationMessage) {
                $items[] = $this->toArray($message);
            }
        }

        return new MessageHistoryPage(
            $items,
            new PaginationMeta($paginator->currentPage(), $paginator->perPage(), $paginator->total()),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(CommunicationMessage $message): array
    {
        return [
            'id' => (int) $message->getKey(),
            'uuid' => (string) $message->uuid,
            'event_key' => (string) $message->event_key,
            'channel' => $message->channel->value,
            'recipient_type' => (string) $message->recipient_type,
            'recipient_id' => $message->recipient_id,
            'recipient_guard' => $message->recipient_guard,
            'recipient_address' => $message->recipient_address,
            'locale' => (string) $message->locale,
            'status' => $message->status->value,
            'subject' => $message->subject,
            'body' => (string) $message->body,
            'provider' => $message->provider,
            'provider_message_id' => $message->provider_message_id,
            'error' => $message->error,
            'attempts' => (int) $message->attempts,
            'created_at' => $message->created_at?->toIso8601String(),
            'sent_at' => $message->sent_at?->toIso8601String(),
            'delivered_at' => $message->delivered_at?->toIso8601String(),
            'read_at' => $message->read_at?->toIso8601String(),
            'failed_at' => $message->failed_at?->toIso8601String(),
        ];
    }
}
