<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Application\Commands;

use Shardevcom\Communications\Application\AdminNotifier;
use Shardevcom\Core\Exceptions\ValidationFailed;

final class TriggerAdminAlert
{
    public function __construct(private readonly AdminNotifier $notifier)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<mixed> $roles
     * @return array{event: string, outcomes: list<array{channel: string, status: string, message_id: ?int, detail: ?string}>}
     */
    public function execute(string $eventKey, array $payload, array $roles = [], mixed $locale = null): array
    {
        $errors = [];

        if ($eventKey === '') {
            $errors['event_key'] = 'The event key is required.';
        }

        if ($locale !== null && ! is_string($locale)) {
            $errors['locale'] = 'The locale must be a string or null.';
        }

        $normalizedRoles = [];

        foreach ($roles as $index => $role) {
            if (! is_string($role) || $role === '') {
                $errors["roles.{$index}"] = 'Each role must be a non-empty string.';
                continue;
            }

            $normalizedRoles[] = $role;
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        $result = $this->notifier->notify($eventKey, $payload, $normalizedRoles, is_string($locale) ? $locale : null);

        return $result->toArray();
    }
}
