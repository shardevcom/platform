<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Domain\Data;

use Shardevcom\Communications\Domain\Enums\MessageStatus;

/**
 * Outcome of one event dispatch, one entry per enabled channel.
 */
final class DispatchResult
{
    /**
     * @param list<DispatchOutcome> $outcomes
     */
    public function __construct(
        public readonly string $eventKey,
        public readonly array $outcomes,
    ) {
    }

    /**
     * @return list<DispatchOutcome>
     */
    public function outcomes(): array
    {
        return $this->outcomes;
    }

    public function isEmpty(): bool
    {
        return $this->outcomes === [];
    }

    public function hasFailures(): bool
    {
        foreach ($this->outcomes as $outcome) {
            if ($outcome->status === MessageStatus::FAILED) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int>
     */
    public function messageIds(): array
    {
        return array_values(array_filter(
            array_map(static fn (DispatchOutcome $outcome): ?int => $outcome->messageId, $this->outcomes),
            static fn (?int $id): bool => $id !== null,
        ));
    }

    /**
     * @return array{event: string, outcomes: list<array{channel: string, status: string, message_id: ?int, detail: ?string}>}
     */
    public function toArray(): array
    {
        return [
            'event' => $this->eventKey,
            'outcomes' => array_map(
                static fn (DispatchOutcome $outcome): array => $outcome->toArray(),
                $this->outcomes,
            ),
        ];
    }
}
