<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Infrastructure\Persistence;

use Shardevcom\Communications\Contracts\TemplateStore;
use Shardevcom\Communications\Domain\Data\MessageTemplate;
use Shardevcom\Communications\Domain\Enums\Channel;
use Shardevcom\Communications\Domain\Enums\TemplateFormat;

final class EloquentTemplateStore implements TemplateStore
{
    public function find(int $eventId, Channel $channel, string $locale): ?MessageTemplate
    {
        $template = CommunicationTemplate::query()
            ->where('event_id', $eventId)
            ->where('channel', $channel->value)
            ->where('locale', $locale)
            ->where('active', true)
            ->first();

        return $template === null ? null : $this->toTemplate($template);
    }

    public function forEvent(int $eventId, bool $activeOnly = false): array
    {
        $query = CommunicationTemplate::query()->where('event_id', $eventId);

        if ($activeOnly) {
            $query->where('active', true);
        }

        return $query
            ->orderBy('channel')
            ->orderBy('locale')
            ->get()
            ->map(fn (CommunicationTemplate $template): MessageTemplate => $this->toTemplate($template))
            ->all();
    }

    public function upsert(
        int $eventId,
        Channel $channel,
        string $locale,
        ?string $subject,
        string $body,
        TemplateFormat $format = TemplateFormat::HTML,
        array $metadata = [],
        bool $active = true,
    ): MessageTemplate {
        $template = CommunicationTemplate::query()->updateOrCreate(
            [
                'event_id' => $eventId,
                'channel' => $channel->value,
                'locale' => $locale,
            ],
            [
                'format' => $format->value,
                'subject' => $subject,
                'body' => $body,
                'metadata' => $metadata === [] ? null : $metadata,
                'active' => $active,
            ],
        );

        return $this->toTemplate($template);
    }

    private function toTemplate(CommunicationTemplate $template): MessageTemplate
    {
        return new MessageTemplate(
            (int) $template->getKey(),
            (int) $template->event_id,
            $template->channel,
            (string) $template->locale,
            $template->format,
            $template->subject,
            (string) $template->body,
            is_array($template->metadata) ? $template->metadata : [],
            (bool) $template->active,
        );
    }
}
