<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Infrastructure\Transport;

use Illuminate\Mail\Message as MailMessage;
use Illuminate\Support\Facades\Mail;
use Shardevcom\Communications\Contracts\ChannelTransport;
use Shardevcom\Communications\Domain\Data\DeliveryResult;
use Shardevcom\Communications\Domain\Data\OutboundMessage;
use Shardevcom\Communications\Domain\Enums\TemplateFormat;

final class MailTransport implements ChannelTransport
{
    public function send(OutboundMessage $message): DeliveryResult
    {
        $address = (string) $message->address;
        $subject = $message->subject ?? (string) config('app.name', 'Notification');

        $metadata = $message->metadata;
        $callback = static function (MailMessage $mail) use ($address, $subject, $metadata): void {
            $mail->to($address)->subject($subject);

            $attachments = $metadata['attachments'] ?? null;

            if (! is_array($attachments)) {
                return;
            }

            foreach ($attachments as $attachment) {
                if (! is_array($attachment)) {
                    continue;
                }

                $name = is_string($attachment['name'] ?? null) ? $attachment['name'] : null;
                $mime = is_string($attachment['mime'] ?? null) ? $attachment['mime'] : null;
                $path = $attachment['path'] ?? null;

                if (is_string($path) && is_file($path)) {
                    $mail->attach($path, [
                        'as' => $name ?? basename($path),
                        'mime' => $mime,
                    ]);
                    continue;
                }

                $data = $attachment['data'] ?? null;

                if (is_string($data)) {
                    $mail->attachData($data, $name ?? 'attachment', ['mime' => $mime]);
                }
            }
        };

        if ($message->format === TemplateFormat::TEXT) {
            Mail::raw($message->body, $callback);
        } else {
            Mail::html($message->body, $callback);
        }

        return DeliveryResult::sent('mail');
    }
}
