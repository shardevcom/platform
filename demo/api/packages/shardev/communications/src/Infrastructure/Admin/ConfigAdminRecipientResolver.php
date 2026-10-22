<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Infrastructure\Admin;

use Shardevcom\Communications\Contracts\AdminRecipientResolver;
use Shardevcom\Communications\Domain\Data\Recipient;
use Shardevcom\Communications\Domain\Enums\Channel;

/**
 * Default resolver backed by `communications.admin.recipients`.
 *
 * It is deliberately simple; replace the binding with a database/role lookup
 * when the platform has a real admin directory.
 */
final class ConfigAdminRecipientResolver implements AdminRecipientResolver
{
    public function resolve(array $roles = []): array
    {
        $configured = config('communications.admin.recipients', []);
        $recipients = [];

        foreach (is_array($configured) ? $configured : [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $entryRoles = array_values(array_filter(
                is_array($entry['roles'] ?? null) ? $entry['roles'] : [],
                is_string(...),
            ));

            if ($roles !== [] && array_intersect($roles, $entryRoles) === []) {
                continue;
            }

            $recipients[] = new Recipient(
                Recipient::TYPE_ADMIN,
                isset($entry['id']) ? (string) $entry['id'] : null,
                isset($entry['guard']) ? (string) $entry['guard'] : 'admin',
                $this->addresses($entry),
                $entryRoles,
                isset($entry['locale']) ? (string) $entry['locale'] : null,
            );
        }

        return $recipients;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, string>
     */
    private function addresses(array $entry): array
    {
        $addresses = [];

        foreach ([Channel::EMAIL->value, Channel::SMS->value, Channel::WHATSAPP->value, Channel::PUSH->value] as $channel) {
            $value = $entry[$channel] ?? null;

            if (is_string($value) && $value !== '') {
                $addresses[$channel] = $value;
            }
        }

        $phone = $entry['phone'] ?? null;

        if (is_string($phone) && $phone !== '') {
            $addresses['phone'] = $phone;
        }

        $pushToken = $entry['push_token'] ?? null;

        if (is_string($pushToken) && $pushToken !== '') {
            $addresses['push_token'] = $pushToken;
        }

        return $addresses;
    }
}
