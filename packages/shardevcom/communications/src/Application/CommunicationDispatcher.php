<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Application;

use Shardevcom\Communications\Contracts\CommunicationQueue;
use Shardevcom\Communications\Contracts\EventConfiguration;
use Shardevcom\Communications\Contracts\MessageLog;
use Shardevcom\Communications\Contracts\PreferenceStore;
use Shardevcom\Communications\Contracts\TemplateStore;
use Shardevcom\Communications\Domain\Data\DispatchOutcome;
use Shardevcom\Communications\Domain\Data\DispatchResult;
use Shardevcom\Communications\Domain\Data\EventDefinition;
use Shardevcom\Communications\Domain\Data\MessageDraft;
use Shardevcom\Communications\Domain\Data\MessageTemplate;
use Shardevcom\Communications\Domain\Data\Recipient;
use Shardevcom\Communications\Domain\Enums\Channel;
use Shardevcom\Communications\Domain\Enums\MessageStatus;
use Shardevcom\Communications\Domain\Enums\TemplateFormat;
use Shardevcom\Core\Exceptions\ResourceNotFound;

/**
 * Public entry point: dispatch(eventKey, recipient, payload).
 *
 * For every channel enabled in the event matrix it:
 * 1. checks opt-out preferences before doing any work;
 * 2. resolves the locale-specific template;
 * 3. resolves the recipient address for that channel;
 * 4. renders the variables and writes a QUEUED history row;
 * 5. queues the row or delivers it synchronously.
 *
 * Skips are silent from the caller's point of view but always recorded in the
 * history with a final status. A missing template configuration is recorded as
 * FAILED, never swallowed.
 */
final class CommunicationDispatcher
{
    public function __construct(
        private readonly EventConfiguration $events,
        private readonly TemplateStore $templates,
        private readonly PreferenceStore $preferences,
        private readonly MessageLog $log,
        private readonly TemplateRenderer $renderer,
        private readonly MessageDeliveryService $delivery,
        private readonly CommunicationQueue $queue,
        private readonly bool $queueEnabled,
        private readonly string $defaultLocale = 'en',
        private readonly string $fallbackLocale = 'en',
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function dispatch(
        string $eventKey,
        Recipient $recipient,
        array $payload = [],
        ?string $locale = null,
    ): DispatchResult {
        $event = $this->events->find($eventKey);

        if ($event === null) {
            throw ResourceNotFound::for('communication_events', $eventKey);
        }

        $locale = $locale ?? $recipient->locale ?? $this->defaultLocale;
        $outcomes = [];

        foreach ($event->channels as $channel) {
            $outcomes[] = $this->dispatchChannel($event, $channel, $recipient, $payload, $locale);
        }

        return new DispatchResult($eventKey, $outcomes);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function dispatchChannel(
        EventDefinition $event,
        Channel $channel,
        Recipient $recipient,
        array $payload,
        string $locale,
    ): DispatchOutcome {
        if ($this->isUnsubscribed($event, $channel, $recipient)) {
            $messageId = $this->log->record($this->draft($event, $channel, $recipient, $locale, null, null, '', []));
            $this->log->markSkipped($messageId, MessageStatus::SKIPPED_UNSUBSCRIBED, 'unsubscribed');

            return new DispatchOutcome($channel, MessageStatus::SKIPPED_UNSUBSCRIBED, $messageId, 'unsubscribed');
        }

        $template = $this->templateFor($event, $channel, $locale);

        if ($template === null) {
            $messageId = $this->log->record($this->draft($event, $channel, $recipient, $locale, null, null, '', []));
            $this->log->markFailed($messageId, 'template_not_found');

            return new DispatchOutcome($channel, MessageStatus::FAILED, $messageId, 'template_not_found');
        }

        $address = $recipient->addressFor($channel);

        if (! $this->hasDestination($channel, $recipient, $address)) {
            $messageId = $this->log->record($this->draft($event, $channel, $recipient, $locale, $template, null, '', []));
            $this->log->markSkipped($messageId, MessageStatus::SKIPPED_MISSING_RECIPIENT, 'missing_recipient');

            return new DispatchOutcome($channel, MessageStatus::SKIPPED_MISSING_RECIPIENT, $messageId, 'missing_recipient');
        }

        $escape = $template->format->isHtml() && $channel === Channel::EMAIL;
        $subject = $template->subject === null
            ? null
            : $this->renderer->render($template->subject, $payload);
        $body = $this->renderer->render($template->body, $payload, $escape);

        $messageId = $this->log->record(
            $this->draft($event, $channel, $recipient, $locale, $template, $address, $body, $payload, $subject),
        );

        if ($this->queueEnabled) {
            $this->queue->push($messageId);

            return new DispatchOutcome($channel, MessageStatus::QUEUED, $messageId);
        }

        $result = $this->delivery->deliver($messageId);

        return new DispatchOutcome($channel, $result->status, $messageId, $result->error);
    }

    private function isUnsubscribed(EventDefinition $event, Channel $channel, Recipient $recipient): bool
    {
        if (! $recipient->honorsPreferences() || $recipient->id === null || $recipient->guard === null) {
            return false;
        }

        return $this->preferences->isOptedOut($recipient->guard, $recipient->id, $channel, $event->key);
    }

    private function templateFor(EventDefinition $event, Channel $channel, string $locale): ?MessageTemplate
    {
        $locales = array_values(array_unique([$locale, $this->fallbackLocale, $this->defaultLocale]));

        foreach ($locales as $candidate) {
            $template = $this->templates->find($event->id, $channel, $candidate);

            if ($template !== null) {
                return $template;
            }
        }

        return null;
    }

    private function hasDestination(Channel $channel, Recipient $recipient, ?string $address): bool
    {
        if ($channel === Channel::IN_APP) {
            return $recipient->id !== null;
        }

        return $address !== null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function draft(
        EventDefinition $event,
        Channel $channel,
        Recipient $recipient,
        string $locale,
        ?MessageTemplate $template,
        ?string $address,
        string $body,
        array $payload,
        ?string $subject = null,
    ): MessageDraft {
        return new MessageDraft(
            $event->key,
            $event->id,
            $template?->id,
            $channel,
            $recipient->type,
            $recipient->id,
            $recipient->guard,
            $address,
            $locale,
            $template?->format ?? TemplateFormat::HTML,
            $subject ?? $template?->subject,
            $body,
            $payload,
            $template?->metadata ?? [],
        );
    }
}
