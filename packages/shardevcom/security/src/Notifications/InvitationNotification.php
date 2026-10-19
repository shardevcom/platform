<?php

declare(strict_types=1);

namespace Shardevcom\Security\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitation email; `$url` is the first-party SPA link carrying the plain token.
 */
final class InvitationNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $url) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('You have been invited')
            ->line('You have been invited to join. Set your password to accept the invitation.')
            ->action('Accept invitation', $this->url);
    }
}
