<?php

declare(strict_types=1);

namespace Shardevcom\Security\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Passwordless one-time code email.
 */
final class OtpNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $code, public readonly int $ttlMinutes) {}

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
            ->subject('Your sign-in code')
            ->line('Use this code to sign in:')
            ->line($this->code)
            ->line('The code expires in '.$this->ttlMinutes.' minutes. If you did not request it, ignore this email.');
    }
}
