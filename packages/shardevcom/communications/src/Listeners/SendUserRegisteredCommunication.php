<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Listeners;

use Illuminate\Auth\Events\Registered;
use Shardevcom\Communications\Application\CommunicationDispatcher;
use Shardevcom\Communications\Contracts\EventConfiguration;
use Shardevcom\Communications\Domain\Data\Recipient;
use Throwable;

/**
 * Bridges Laravel's Registered event to the communications event catalogue.
 *
 * The platform is inferred from the registered model so admin/employee
 * registrations keep their own guard and preference namespace. If the target
 * event is not configured (or is disabled), registration is untouched.
 */
final class SendUserRegisteredCommunication
{
    public function __construct(
        private readonly CommunicationDispatcher $dispatcher,
        private readonly EventConfiguration $events,
    ) {
    }

    public function handle(Registered $event): void
    {
        if (! (bool) config('communications.listeners.registered.enabled', true)) {
            return;
        }

        $eventKey = (string) config('communications.listeners.registered.event', 'user.registered');

        if ($this->events->find($eventKey) === null) {
            return;
        }

        $user = $event->user;
        $guard = $this->guardFor($user);

        if ($guard === null) {
            return;
        }

        $id = method_exists($user, 'getAuthIdentifier')
            ? $user->getAuthIdentifier()
            : $this->attribute($user, 'id');

        if (! is_scalar($id) || (string) $id === '') {
            return;
        }

        $addresses = $this->addresses($user);

        try {
            $this->dispatcher->dispatch(
                $eventKey,
                Recipient::user((string) $id, $guard, $addresses),
                [
                    'name' => $this->attribute($user, 'name'),
                    'email' => $this->attribute($user, 'email'),
                ],
            );
        } catch (Throwable $exception) {
            // Registration must not fail because a communication template is
            // misconfigured; the dispatcher already records delivery failures.
            report($exception);
        }
    }

    private function guardFor(object $user): ?string
    {
        $platforms = config('platforms.platforms', []);

        foreach (is_array($platforms) ? $platforms : [] as $guard => $platform) {
            $model = is_array($platform) ? ($platform['model'] ?? null) : null;

            if (is_string($model) && class_exists($model) && $user instanceof $model) {
                return (string) ($platform['guard'] ?? $guard);
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function addresses(object $user): array
    {
        $addresses = [];

        foreach (['email', 'phone', 'push_token'] as $attribute) {
            $value = $this->attribute($user, $attribute);

            if (is_string($value) && trim($value) !== '') {
                $addresses[$attribute] = $value;
            }
        }

        return $addresses;
    }

    private function attribute(object $user, string $attribute): mixed
    {
        if (! method_exists($user, 'getAttribute')) {
            return null;
        }

        return $user->getAttribute($attribute);
    }
}
