<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Application\Commands;

use Shardevcom\Communications\Application\PreferenceManager;
use Shardevcom\Communications\Domain\Enums\Channel;
use Shardevcom\Core\Exceptions\ValidationFailed;

final class SetPreference
{
    public function __construct(private readonly PreferenceManager $preferences)
    {
    }

    public function execute(
        string $guard,
        string $userId,
        bool $optedOut,
        mixed $channel = null,
        mixed $eventKey = null,
        mixed $reason = null,
    ): void {
        $errors = [];

        if ($guard === '' || $userId === '') {
            throw new ValidationFailed(['user' => 'An authenticated user is required.']);
        }

        $parsedChannel = null;

        if ($channel !== null) {
            $parsedChannel = is_string($channel) ? Channel::tryFrom($channel) : null;

            if ($parsedChannel === null) {
                $errors['channel'] = 'The channel is invalid.';
            }
        }

        $parsedEvent = null;

        if ($eventKey !== null) {
            if (! is_string($eventKey) || preg_match('/^[A-Za-z0-9._-]+$/', $eventKey) !== 1) {
                $errors['event_key'] = 'The event key is invalid.';
            } else {
                $parsedEvent = $eventKey;
            }
        }

        if ($reason !== null && (! is_string($reason) || mb_strlen($reason) > 500)) {
            $errors['reason'] = 'The reason must be a string of at most 500 characters.';
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        if ($optedOut) {
            $this->preferences->optOut($guard, $userId, $parsedChannel, $parsedEvent, is_string($reason) ? $reason : null);
        } else {
            $this->preferences->optIn($guard, $userId, $parsedChannel, $parsedEvent);
        }
    }
}
