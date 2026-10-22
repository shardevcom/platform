<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * One opt-out row. There is no `subscribed` flag: the absence of a matching
 * row is the opt-in default, and `optIn()` removes the row.
 *
 * @property int $id
 * @property string $guard
 * @property string $user_id
 * @property string $channel
 * @property string $event_key
 * @property string|null $reason
 * @property \Illuminate\Support\Carbon $opted_out_at
 */
final class CommunicationPreference extends Model
{
    protected $table = 'communication_preferences';

    /** @var list<string> */
    protected $fillable = ['guard', 'user_id', 'channel', 'event_key', 'reason', 'opted_out_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['opted_out_at' => 'datetime'];
    }
}
