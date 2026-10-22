<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;
use Shardevcom\Communications\Domain\Enums\Channel;

/**
 * @property int $id
 * @property int $event_id
 * @property Channel $channel
 * @property bool $enabled
 */
final class CommunicationEventChannel extends Model
{
    protected $table = 'communication_event_channels';

    /** @var list<string> */
    protected $fillable = ['event_id', 'channel', 'enabled'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'enabled' => 'boolean',
        ];
    }
}
