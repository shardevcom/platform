<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Application\Commands;

use Shardevcom\Communications\Application\Queries\ListTemplates;
use Shardevcom\Communications\Contracts\EventConfiguration;
use Shardevcom\Communications\Contracts\TemplateStore;
use Shardevcom\Communications\Domain\Data\EventDefinition;
use Shardevcom\Communications\Domain\Data\MessageTemplate;
use Shardevcom\Communications\Domain\Enums\Channel;
use Shardevcom\Communications\Domain\Enums\TemplateFormat;
use Shardevcom\Core\Exceptions\ResourceNotFound;
use Shardevcom\Core\Exceptions\ValidationFailed;

final class SaveTemplate
{
    public function __construct(
        private readonly EventConfiguration $events,
        private readonly TemplateStore $templates,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function execute(string $eventKey, array $input): array
    {
        $errors = [];
        $event = $this->requireEvent($eventKey);

        $channel = $this->channel($input['channel'] ?? null, $errors);
        $locale = $this->locale($input['locale'] ?? null, $errors);
        $format = $this->format($input['format'] ?? 'html', $errors);
        $body = $input['body'] ?? null;
        $subject = $input['subject'] ?? null;
        $metadata = $input['metadata'] ?? [];
        $active = $input['active'] ?? true;

        if (! is_string($body) || trim($body) === '') {
            $errors['body'] = 'The body is required.';
        }

        if ($subject !== null && ! is_string($subject)) {
            $errors['subject'] = 'The subject must be a string or null.';
        }

        if (! is_array($metadata)) {
            $errors['metadata'] = 'The metadata must be an object.';
        }

        if (! is_bool($active)) {
            $errors['active'] = 'The active flag must be a boolean.';
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        $template = $this->templates->upsert(
            $event->id,
            $channel,
            $locale,
            $subject,
            (string) $body,
            $format,
            $metadata,
            $active,
        );

        return ListTemplates::templateToArray($template);
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
     * @param array<string, mixed> $errors
     */
    private function channel(mixed $value, array &$errors): Channel
    {
        $channel = is_string($value) ? Channel::tryFrom($value) : null;

        if ($channel === null) {
            $errors['channel'] = 'The channel is invalid.';
            return Channel::EMAIL;
        }

        return $channel;
    }

    /**
     * @param array<string, mixed> $errors
     */
    private function locale(mixed $value, array &$errors): string
    {
        if (! is_string($value) || preg_match('/^[a-z]{2}(?:[_-][A-Za-z]{2})?$/', $value) !== 1) {
            $errors['locale'] = 'The locale must look like en, es or en_US.';
            return 'en';
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $errors
     */
    private function format(mixed $value, array &$errors): TemplateFormat
    {
        $format = is_string($value) ? TemplateFormat::tryFrom($value) : null;

        if ($format === null) {
            $errors['format'] = 'The format must be html or text.';
            return TemplateFormat::HTML;
        }

        return $format;
    }
}
