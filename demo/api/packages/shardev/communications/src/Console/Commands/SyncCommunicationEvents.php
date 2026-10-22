<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Console\Commands;

use Illuminate\Console\Command;
use Shardevcom\Communications\Contracts\EventConfiguration;
use Shardevcom\Communications\Contracts\TemplateStore;
use Shardevcom\Communications\Domain\Enums\Channel;
use Shardevcom\Communications\Domain\Enums\EventCategory;
use Shardevcom\Communications\Domain\Enums\TemplateFormat;
use Throwable;

/**
 * Idempotent bootstrap for events and templates declared in config.
 *
 * Operators can keep the default matrix/templates in `communications.events`
 * and run `php artisan communications:sync` on deploy. The command never
 * deletes events omitted from config; it only creates/updates.
 */
final class SyncCommunicationEvents extends Command
{
    protected $signature = 'communications:sync {--event=* : Only sync these event keys}';

    protected $description = 'Create or update communication events, channel matrix and templates from config';

    public function handle(EventConfiguration $events, TemplateStore $templates): int
    {
        $configured = config('communications.events', []);

        if (! is_array($configured)) {
            $this->error('communications.events must be an array.');

            return self::FAILURE;
        }

        $only = $this->option('event');
        $only = is_array($only) ? array_values(array_filter($only, 'is_string')) : [];

        foreach ($configured as $key => $definition) {
            if (! is_string($key) || ! is_array($definition)) {
                $this->error('Each event must be keyed by a string and contain an array.');

                return self::FAILURE;
            }

            if ($only !== [] && ! in_array($key, $only, true)) {
                continue;
            }

            try {
                $this->syncEvent($events, $templates, $key, $definition);
                $this->info("Synced [{$key}].");
            } catch (Throwable $exception) {
                $this->error("[{$key}] {$exception->getMessage()}");

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function syncEvent(EventConfiguration $events, TemplateStore $templates, string $key, array $definition): void
    {
        $category = EventCategory::tryFrom((string) ($definition['category'] ?? 'transactional'));

        if ($category === null) {
            throw new \InvalidArgumentException('category must be transactional or admin.');
        }

        $channels = [];

        foreach ((array) ($definition['channels'] ?? []) as $channel) {
            $parsed = is_string($channel) ? Channel::tryFrom($channel) : null;

            if ($parsed === null) {
                throw new \InvalidArgumentException('Unknown channel in channels[].');
            }

            $channels[] = $parsed;
        }

        $event = $events->save(
            $key,
            (string) ($definition['name'] ?? $key),
            isset($definition['description']) ? (string) $definition['description'] : null,
            $category,
            (bool) ($definition['active'] ?? true),
            $channels,
        );

        foreach ((array) ($definition['templates'] ?? []) as $channelKey => $locales) {
            $channel = is_string($channelKey) ? Channel::tryFrom($channelKey) : null;

            if ($channel === null || ! is_array($locales)) {
                throw new \InvalidArgumentException('templates must be keyed by channel and contain locales.');
            }

            foreach ($locales as $locale => $template) {
                if (! is_string($locale) || ! is_array($template)) {
                    throw new \InvalidArgumentException('Each template must be keyed by locale and contain an array.');
                }

                $format = TemplateFormat::tryFrom((string) ($template['format'] ?? 'html'));

                if ($format === null) {
                    throw new \InvalidArgumentException('template.format must be html or text.');
                }

                $body = $template['body'] ?? null;

                if (! is_string($body) || trim($body) === '') {
                    throw new \InvalidArgumentException('template.body is required.');
                }

                $metadata = $template['metadata'] ?? [];

                if (! is_array($metadata)) {
                    throw new \InvalidArgumentException('template.metadata must be an object.');
                }

                $templates->upsert(
                    $event->id,
                    $channel,
                    $locale,
                    isset($template['subject']) ? (string) $template['subject'] : null,
                    $body,
                    $format,
                    $metadata,
                    (bool) ($template['active'] ?? true),
                );
            }
        }
    }
}
