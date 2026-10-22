<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Infrastructure\Transport;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Shardevcom\Communications\Contracts\ChannelTransport;
use Shardevcom\Communications\Domain\Data\DeliveryResult;
use Shardevcom\Communications\Domain\Data\OutboundMessage;
use Shardevcom\Communications\Infrastructure\Notifications\InAppCommunicationNotification;

/**
 * Writes to Laravel's `notifications` table, which the platform's existing
 * auth/notifications endpoints already expose to the authenticated user.
 */
final class InAppTransport implements ChannelTransport
{
    public function send(OutboundMessage $message): DeliveryResult
    {
        $modelClass = $this->notifiableModel($message->recipientGuard);

        if ($modelClass === null || $message->recipientId === null) {
            return DeliveryResult::failed('in_app_recipient_not_resolved');
        }

        /** @var Model|null $notifiable */
        $notifiable = $modelClass::query()->find($message->recipientId);

        if ($notifiable === null) {
            return DeliveryResult::failed('in_app_recipient_not_found');
        }

        Notification::send($notifiable, new InAppCommunicationNotification(
            $message->subject ?? $message->eventKey ?? 'Notification',
            $message->body,
            $message->metadata,
        ));

        return DeliveryResult::sent('database');
    }

    /**
     * @return class-string<Model>|null
     */
    private function notifiableModel(?string $guard): ?string
    {
        $configured = config('communications.channels.in_app.model');

        if (is_string($configured) && is_a($configured, Model::class, true)) {
            return $configured;
        }

        if ($guard === null) {
            return null;
        }

        $platform = config('platforms.platforms.'.$guard);
        $model = is_array($platform) ? ($platform['model'] ?? null) : null;

        return is_string($model) && is_a($model, Model::class, true) ? $model : null;
    }
}
