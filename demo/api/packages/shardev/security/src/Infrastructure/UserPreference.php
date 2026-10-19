<?php

declare(strict_types=1);

namespace Shardevcom\Security\Infrastructure;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-user, per-guard preferences (theme, language, notification channels).
 *
 * It carries no business rules: the security controllers merge defaults and
 * validate input before writing here.
 *
 * @property string $guard
 * @property int|string $user_id
 * @property string|null $theme
 * @property string|null $language
 * @property array<string, mixed>|null $notifications
 */
final class UserPreference extends Model
{
    protected $table = 'platform_user_preferences';

    /** @var list<string> */
    protected $fillable = ['guard', 'user_id', 'theme', 'language', 'notifications'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['notifications' => 'array'];
    }
}
