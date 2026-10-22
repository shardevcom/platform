<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Shardevcom\Communications\Domain\Enums\EventCategory;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property EventCategory $category
 * @property bool $active
 */
final class CommunicationEvent extends Model
{
    protected $table = 'communication_events';

    /** @var list<string> */
    protected $fillable = ['key', 'name', 'description', 'category', 'active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => EventCategory::class,
            'active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<CommunicationEventChannel, $this>
     */
    public function channels(): HasMany
    {
        return $this->hasMany(CommunicationEventChannel::class, 'event_id');
    }

    /**
     * @return HasMany<CommunicationTemplate, $this>
     */
    public function templates(): HasMany
    {
        return $this->hasMany(CommunicationTemplate::class, 'event_id');
    }
}
