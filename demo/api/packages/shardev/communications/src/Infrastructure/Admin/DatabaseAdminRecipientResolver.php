<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Infrastructure\Admin;

use Illuminate\Database\Eloquent\Model;
use Shardevcom\Communications\Contracts\AdminRecipientResolver;
use Shardevcom\Communications\Domain\Data\Recipient;
use Shardevcom\Communications\Domain\Enums\Channel;

/**
 * Opt-in database resolver for platforms that keep admin roles in a column.
 *
 * This deliberately assumes a single role column, not a particular role
 * package. Hosts with a many-to-many/team model can keep using the port and
 * bind their own resolver.
 */
final class DatabaseAdminRecipientResolver implements AdminRecipientResolver
{
    public function resolve(array $roles = []): array
    {
        $settings = config('communications.admin.database', []);

        if (! is_array($settings)) {
            return [];
        }

        $guard = (string) ($settings['guard'] ?? 'admin');
        $modelClass = $settings['model'] ?? config('platforms.platforms.'.$guard.'.model');

        if (! is_string($modelClass) || ! class_exists($modelClass)) {
            return [];
        }

        /** @var Model $model */
        $model = new $modelClass();
        $query = $model->newQuery();

        $roleColumn = $this->column($settings, 'role_column', 'role');

        if ($roles !== []) {
            $query->whereIn($roleColumn, $roles);
        }

        $activeColumn = $settings['active_column'] ?? null;

        if (is_string($activeColumn) && $activeColumn !== '') {
            $query->where($activeColumn, true);
        }

        $recipients = [];

        foreach ($query->get() as $row) {
            if (! $row instanceof Model) {
                continue;
            }

            $id = $this->value($row, $this->column($settings, 'id_column', 'id'));

            if (! is_scalar($id)) {
                continue;
            }

            $rowRoles = $this->roles($row, $roleColumn);

            if ($roles !== [] && array_intersect($roles, $rowRoles) === []) {
                continue;
            }

            $recipients[] = new Recipient(
                Recipient::TYPE_ADMIN,
                (string) $id,
                $guard,
                $this->addresses($row, $settings),
                $rowRoles,
                $this->stringValue($row, $this->column($settings, 'locale_column', 'locale')),
            );
        }

        return $recipients;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, string>
     */
    private function addresses(Model $row, array $settings): array
    {
        $addresses = [];

        foreach ([Channel::EMAIL->value, Channel::SMS->value, Channel::WHATSAPP->value, Channel::PUSH->value] as $channel) {
            $value = $this->stringValue($row, $this->column($settings, $channel.'_column', $channel));

            if ($value !== null && $value !== '') {
                $addresses[$channel] = $value;
            }
        }

        $phone = $this->stringValue($row, $this->column($settings, 'phone_column', 'phone'));

        if ($phone !== null && $phone !== '') {
            $addresses['phone'] = $phone;
        }

        $pushToken = $this->stringValue($row, $this->column($settings, 'push_token_column', 'push_token'));

        if ($pushToken !== null && $pushToken !== '') {
            $addresses['push_token'] = $pushToken;
        }

        return $addresses;
    }

    /**
     * @return list<string>
     */
    private function roles(Model $row, string $column): array
    {
        $value = $this->value($row, $column);

        if (is_string($value)) {
            return $value === '' ? [] : [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function column(array $settings, string $key, string $default): string
    {
        $value = $settings[$key] ?? $default;

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function value(Model $row, string $column): mixed
    {
        return $row->getAttribute($column);
    }

    private function stringValue(Model $row, string $column): ?string
    {
        $value = $this->value($row, $column);

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
