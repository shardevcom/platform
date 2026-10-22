<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Infrastructure\Notifications;

use Illuminate\Notifications\Notification;

final class InAppCommunicationNotification extends Notification
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly string $title,
        private readonly string $body,
        private readonly array $data = [],
    ) {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{title: string, body: string, data: array<string, mixed>}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
        ];
    }
}
