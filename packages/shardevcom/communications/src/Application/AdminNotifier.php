<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Application;

use Shardevcom\Communications\Contracts\AdminRecipientResolver;
use Shardevcom\Communications\Contracts\EventConfiguration;
use Shardevcom\Communications\Domain\Data\DispatchOutcome;
use Shardevcom\Communications\Domain\Data\DispatchResult;
use Shardevcom\Core\Exceptions\BusinessRuleViolation;
use Shardevcom\Core\Exceptions\ResourceNotFound;

/**
 * Internal notifications for administrators.
 *
 * The event must be configured with category=admin. Its channel matrix decides
 * whether the alert goes to the in-app panel, the administrative mailbox, or
 * both; recipients and roles come from the AdminRecipientResolver port.
 */
final class AdminNotifier
{
    public function __construct(
        private readonly CommunicationDispatcher $dispatcher,
        private readonly AdminRecipientResolver $recipients,
        private readonly EventConfiguration $events,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $roles
     */
    public function notify(
        string $eventKey,
        array $payload = [],
        array $roles = [],
        ?string $locale = null,
    ): DispatchResult {
        $event = $this->events->find($eventKey);

        if ($event === null) {
            throw ResourceNotFound::for('communication_events', $eventKey);
        }

        if (! $event->category->isAdmin()) {
            throw new BusinessRuleViolation(
                "Event [{$eventKey}] is not an administrative event.",
                ['event' => $eventKey],
            );
        }

        $outcomes = [];

        foreach ($this->recipients->resolve($roles) as $recipient) {
            $result = $this->dispatcher->dispatch($eventKey, $recipient, $payload, $locale);

            foreach ($result->outcomes() as $outcome) {
                $outcomes[] = $outcome;
            }
        }

        /** @var list<DispatchOutcome> $outcomes */
        return new DispatchResult($eventKey, $outcomes);
    }
}
