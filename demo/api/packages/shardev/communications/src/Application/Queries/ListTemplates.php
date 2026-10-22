<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Application\Queries;

use Shardevcom\Communications\Contracts\EventConfiguration;
use Shardevcom\Communications\Contracts\TemplateStore;
use Shardevcom\Communications\Domain\Data\EventDefinition;
use Shardevcom\Communications\Domain\Data\MessageTemplate;
use Shardevcom\Core\Exceptions\ResourceNotFound;

final class ListTemplates
{
    public function __construct(
        private readonly EventConfiguration $events,
        private readonly TemplateStore $templates,
    ) {
    }

    /**
     * @return array{event: string, templates: list<array<string, mixed>>}
     */
    public function execute(string $eventKey): array
    {
        $event = $this->requireEvent($eventKey);

        return [
            'event' => $event->key,
            'templates' => array_map(
                static fn (MessageTemplate $template): array => self::templateToArray($template),
                $this->templates->forEvent($event->id),
            ),
        ];
    }

    private function requireEvent(string $eventKey): EventDefinition
    {
        foreach ($this->events->all() as $event) {
            if ($event->key === $eventKey) {
                return $event;
            }
        }

        throw ResourceNotFound::for('communication_events', $eventKey);
    }

    /**
     * @return array<string, mixed>
     */
    public static function templateToArray(MessageTemplate $template): array
    {
        return [
            'id' => $template->id,
            'event_id' => $template->eventId,
            'channel' => $template->channel->value,
            'locale' => $template->locale,
            'format' => $template->format->value,
            'subject' => $template->subject,
            'body' => $template->body,
            'metadata' => $template->metadata,
            'active' => $template->active,
        ];
    }
}
