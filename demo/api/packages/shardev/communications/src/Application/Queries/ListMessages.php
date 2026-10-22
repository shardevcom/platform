<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Application\Queries;

use DateTimeImmutable;
use Shardevcom\Communications\Contracts\MessageHistory;
use Shardevcom\Communications\Domain\Data\MessageHistoryFilter;
use Shardevcom\Communications\Domain\Data\MessageHistoryPage;
use Shardevcom\Communications\Domain\Enums\Channel;
use Shardevcom\Communications\Domain\Enums\MessageStatus;
use Shardevcom\Core\Exceptions\ValidationFailed;

final class ListMessages
{
    public function __construct(private readonly MessageHistory $history)
    {
    }

    /**
     * @param array<string, mixed> $input
     */
    public function execute(array $input): MessageHistoryPage
    {
        $errors = [];

        $channel = null;

        if (isset($input['channel']) && $input['channel'] !== '') {
            $channel = is_string($input['channel']) ? Channel::tryFrom($input['channel']) : null;

            if ($channel === null) {
                $errors['channel'] = 'The channel is invalid.';
            }
        }

        $status = null;

        if (isset($input['status']) && $input['status'] !== '') {
            $status = is_string($input['status']) ? MessageStatus::tryFrom($input['status']) : null;

            if ($status === null) {
                $errors['status'] = 'The status is invalid.';
            }
        }

        $from = $this->date($input['from'] ?? null, 'from', $errors);
        $to = $this->date($input['to'] ?? null, 'to', $errors);
        $page = $this->positiveInt($input['page'] ?? 1, 'page', $errors);
        $perPage = $this->positiveInt($input['per_page'] ?? 20, 'per_page', $errors);

        if ($perPage > 100) {
            $errors['per_page'] = 'The page size may not be greater than 100.';
        }

        if ($from !== null && $to !== null && $from > $to) {
            $errors['from'] = 'The from date must be before the to date.';
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        return $this->history->paginate(
            new MessageHistoryFilter(
                eventKey: $this->stringOrNull($input['event_key'] ?? null),
                channel: $channel,
                status: $status,
                recipientType: $this->stringOrNull($input['recipient_type'] ?? null),
                recipientId: $this->stringOrNull($input['recipient_id'] ?? null),
                recipientGuard: $this->stringOrNull($input['recipient_guard'] ?? null),
                from: $from,
                to: $to,
            ),
            $page,
            $perPage,
        );
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $errors
     */
    private function date(mixed $value, string $field, array &$errors): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            $errors[$field] = 'The date must be an ISO-8601 string.';
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            $errors[$field] = 'The date must be an ISO-8601 string.';
            return null;
        }
    }

    /**
     * @param array<string, mixed> $errors
     */
    private function positiveInt(mixed $value, string $field, array &$errors): int
    {
        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }

        if (! is_int($value) || $value < 1) {
            $errors[$field] = 'The value must be a positive integer.';
            return 1;
        }

        return $value;
    }
}
