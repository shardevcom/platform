<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;
use Shardevcom\Communications\Domain\Enums\Channel;
use Shardevcom\Communications\Domain\Enums\TemplateFormat;

/**
 * @property int $id
 * @property int $event_id
 * @property Channel $channel
 * @property string $locale
 * @property TemplateFormat $format
 * @property string|null $subject
 * @property string $body
 * @property array<string, mixed>|null $metadata
 * @property bool $active
 */
final class CommunicationTemplate extends Model
{
    protected $table = 'communication_templates';

    /** @var list<string> */
    protected $fillable = [
        'event_id',
        'channel',
        'locale',
        'format',
        'subject',
        'body',
        'metadata',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'format' => TemplateFormat::class,
            'metadata' => 'array',
            'active' => 'boolean',
        ];
    }
}
