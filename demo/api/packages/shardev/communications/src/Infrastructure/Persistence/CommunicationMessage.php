<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;
use Shardevcom\Communications\Domain\Enums\Channel;
use Shardevcom\Communications\Domain\Enums\MessageStatus;
use Shardevcom\Communications\Domain\Enums\TemplateFormat;

/**
 * Delivery history row: one per event x channel x recipient attempt.
 *
 * @property int $id
 * @property string $uuid
 * @property string $event_key
 * @property int|null $event_id
 * @property int|null $template_id
 * @property Channel $channel
 * @property string $recipient_type
 * @property string|null $recipient_id
 * @property string|null $recipient_guard
 * @property string|null $recipient_address
 * @property string $locale
 * @property TemplateFormat $format
 * @property MessageStatus $status
 * @property string|null $subject
 * @property string $body
 * @property array<string, mixed>|null $payload
 * @property array<string, mixed>|null $metadata
 * @property string|null $provider
 * @property string|null $provider_message_id
 * @property string|null $error
 * @property int $attempts
 */
final class CommunicationMessage extends Model
{
    protected $table = 'communication_messages';

    /** @var list<string> */
    protected $fillable = [
        'uuid',
        'event_key',
        'event_id',
        'template_id',
        'channel',
        'recipient_type',
        'recipient_id',
        'recipient_guard',
        'recipient_address',
        'locale',
        'format',
        'status',
        'subject',
        'body',
        'payload',
        'metadata',
        'provider',
        'provider_message_id',
        'error',
        'attempts',
        'queued_at',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'skipped_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'format' => TemplateFormat::class,
            'status' => MessageStatus::class,
            'payload' => 'array',
            'metadata' => 'array',
            'attempts' => 'integer',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
            'skipped_at' => 'datetime',
        ];
    }
}
