<?php

declare(strict_types=1);

namespace Tests\Unit\Communications;

use PHPUnit\Framework\TestCase;
use Shardevcom\Communications\Application\CommunicationDispatcher;
use Shardevcom\Communications\Application\MessageDeliveryService;
use Shardevcom\Communications\Application\TemplateRenderer;
use Shardevcom\Communications\Contracts\ChannelTransport;
use Shardevcom\Communications\Contracts\CommunicationQueue;
use Shardevcom\Communications\Contracts\EventConfiguration;
use Shardevcom\Communications\Contracts\MessageLog;
use Shardevcom\Communications\Contracts\PreferenceStore;
use Shardevcom\Communications\Contracts\TemplateStore;
use Shardevcom\Communications\Contracts\TransportRegistry;
use Shardevcom\Communications\Domain\Data\DeliveryResult;
use Shardevcom\Communications\Domain\Data\EventDefinition;
use Shardevcom\Communications\Domain\Data\MessageDraft;
use Shardevcom\Communications\Domain\Data\MessageTemplate;
use Shardevcom\Communications\Domain\Data\OutboundMessage;
use Shardevcom\Communications\Domain\Data\PendingMessage;
use Shardevcom\Communications\Domain\Data\Recipient;
use Shardevcom\Communications\Domain\Enums\Channel;
use Shardevcom\Communications\Domain\Enums\EventCategory;
use Shardevcom\Communications\Domain\Enums\MessageStatus;
use Shardevcom\Communications\Domain\Enums\TemplateFormat;
use Shardevcom\Core\Exceptions\ResourceNotFound;

final class CommunicationDispatcherTest extends TestCase
{
    public function test_dispatches_every_enabled_channel_and_renders_variables(): void
    {
        [$dispatcher, $templates, , $log, $transports] = $this->dispatcher([
            new EventDefinition(1, 'user.registered', 'User registered', EventCategory::TRANSACTIONAL, [
                Channel::EMAIL,
                Channel::WHATSAPP,
            ]),
        ]);

        $templates->put(1, Channel::EMAIL, 'en', 'Welcome {{ name }}', 'Hi {{ name }}');
        $templates->put(1, Channel::WHATSAPP, 'en', null, 'Hola {{ name }}');

        $result = $dispatcher->dispatch(
            'user.registered',
            Recipient::user('42', 'web', [
                'email' => 'ana@example.com',
                'whatsapp' => '+34600000000',
            ]),
            ['name' => 'Ana'],
        );

        $this->assertCount(2, $result->outcomes());
        $this->assertSame([MessageStatus::SENT, MessageStatus::SENT], array_map(
            static fn ($outcome): MessageStatus => $outcome->status,
            $result->outcomes(),
        ));
        $this->assertSame([MessageStatus::SENT, MessageStatus::SENT], array_values($log->statuses));
        $this->assertCount(2, $transports->sent());
        $this->assertSame('Welcome Ana', $log->drafts[1]->subject);
        $this->assertSame('Hi Ana', $log->drafts[1]->body);
        $this->assertSame('Hola Ana', $log->drafts[2]->body);
    }

    public function test_skips_unsubscribed_channel_and_records_the_reason(): void
    {
        [$dispatcher, $templates, $preferences, $log, $transports] = $this->dispatcher([
            new EventDefinition(1, 'user.registered', 'User registered', EventCategory::TRANSACTIONAL, [
                Channel::EMAIL,
                Channel::WHATSAPP,
            ]),
        ]);

        $templates->put(1, Channel::EMAIL, 'en', 'Hi', 'Body');
        $templates->put(1, Channel::WHATSAPP, 'en', null, 'Body');
        $preferences->optOut('web', '42', Channel::WHATSAPP, 'user.registered', 'user request');

        $result = $dispatcher->dispatch(
            'user.registered',
            Recipient::user('42', 'web', [
                'email' => 'ana@example.com',
                'whatsapp' => '+34600000000',
            ]),
        );

        $this->assertSame(MessageStatus::SENT, $result->outcomes()[0]->status);
        $this->assertSame(MessageStatus::SKIPPED_UNSUBSCRIBED, $result->outcomes()[1]->status);
        $this->assertSame(
            MessageStatus::SKIPPED_UNSUBSCRIBED,
            $log->statuses[$result->outcomes()[1]->messageId],
        );
        $this->assertCount(1, $transports->sent());
    }

    public function test_a_global_opt_out_skips_all_channels_and_events(): void
    {
        [$dispatcher, $templates, $preferences, $log] = $this->dispatcher([
            new EventDefinition(1, 'user.registered', 'User registered', EventCategory::TRANSACTIONAL, [
                Channel::EMAIL,
                Channel::WHATSAPP,
            ]),
        ]);

        $templates->put(1, Channel::EMAIL, 'en', 'Hi', 'Body');
        $templates->put(1, Channel::WHATSAPP, 'en', null, 'Body');
        $preferences->optOut('web', '42');

        $dispatcher->dispatch(
            'user.registered',
            Recipient::user('42', 'web', [
                'email' => 'ana@example.com',
                'whatsapp' => '+34600000000',
            ]),
        );

        $this->assertSame(
            [MessageStatus::SKIPPED_UNSUBSCRIBED, MessageStatus::SKIPPED_UNSUBSCRIBED],
            array_values($log->statuses),
        );
    }

    public function test_missing_template_is_recorded_as_failed(): void
    {
        [$dispatcher, , , $log] = $this->dispatcher([
            new EventDefinition(1, 'user.registered', 'User registered', EventCategory::TRANSACTIONAL, [
                Channel::EMAIL,
            ]),
        ]);

        $result = $dispatcher->dispatch(
            'user.registered',
            Recipient::user('42', 'web', ['email' => 'ana@example.com']),
        );

        $this->assertSame(MessageStatus::FAILED, $result->outcomes()[0]->status);
        $this->assertSame('template_not_found', $result->outcomes()[0]->detail);
        $this->assertSame(MessageStatus::FAILED, $log->statuses[1]);
    }

    public function test_missing_address_is_skipped_without_calling_a_transport(): void
    {
        [$dispatcher, $templates, , $log, $transports] = $this->dispatcher([
            new EventDefinition(1, 'user.registered', 'User registered', EventCategory::TRANSACTIONAL, [
                Channel::EMAIL,
            ]),
        ]);

        $templates->put(1, Channel::EMAIL, 'en', 'Hi', 'Body');

        $result = $dispatcher->dispatch('user.registered', Recipient::user('42', 'web'));

        $this->assertSame(MessageStatus::SKIPPED_MISSING_RECIPIENT, $result->outcomes()[0]->status);
        $this->assertCount(0, $transports->sent());
        $this->assertSame(MessageStatus::SKIPPED_MISSING_RECIPIENT, $log->statuses[1]);
    }

    public function test_falls_back_to_the_configured_locale(): void
    {
        [$dispatcher, $templates, , $log] = $this->dispatcher(
            [new EventDefinition(1, 'user.registered', 'User registered', EventCategory::TRANSACTIONAL, [Channel::EMAIL])],
            fallbackLocale: 'es',
        );

        $templates->put(1, Channel::EMAIL, 'es', 'Hola', 'Cuerpo en español');

        $dispatcher->dispatch(
            'user.registered',
            Recipient::user('42', 'web', ['email' => 'ana@example.com'], 'fr'),
        );

        $this->assertSame('Cuerpo en español', $log->drafts[1]->body);
    }

    public function test_queue_mode_logs_queued_and_pushes_the_message(): void
    {
        [$dispatcher, $templates, , $log, $transports, $queue] = $this->dispatcher(
            [new EventDefinition(1, 'user.registered', 'User registered', EventCategory::TRANSACTIONAL, [Channel::EMAIL])],
            queueEnabled: true,
        );

        $templates->put(1, Channel::EMAIL, 'en', 'Hi', 'Body');

        $result = $dispatcher->dispatch(
            'user.registered',
            Recipient::user('42', 'web', ['email' => 'ana@example.com']),
        );

        $this->assertSame(MessageStatus::QUEUED, $result->outcomes()[0]->status);
        $this->assertSame([1], $queue->pushed);
        $this->assertSame(MessageStatus::QUEUED, $log->statuses[1]);
        $this->assertCount(0, $transports->sent());
    }

    public function test_worker_delivery_rethrows_failures_so_the_queue_retries(): void
    {
        $log = new FakeMessageLog();
        $delivery = new MessageDeliveryService(
            $log,
            new FakePreferenceStore(),
            new FailingTransportRegistry(),
        );

        $messageId = $log->record(new MessageDraft(
            'user.registered',
            1,
            null,
            Channel::EMAIL,
            Recipient::TYPE_USER,
            '42',
            'web',
            'ana@example.com',
            'en',
            TemplateFormat::HTML,
            'Hi',
            'Body',
            [],
            [],
        ));

        try {
            $delivery->deliver($messageId, throwOnFailure: true);
            $this->fail('Expected the worker delivery to throw.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('smtp_down', $exception->getMessage());
        }

        $this->assertSame(MessageStatus::FAILED, $log->statuses[$messageId]);
        $this->assertSame('smtp_down', $log->errors[$messageId]);
    }

    public function test_unknown_event_throws_resource_not_found(): void
    {
        [$dispatcher] = $this->dispatcher([]);

        $this->expectException(ResourceNotFound::class);

        $dispatcher->dispatch('missing.event', Recipient::user('42', 'web'));
    }

    /**
     * @param list<EventDefinition> $events
     * @return array{CommunicationDispatcher, FakeTemplateStore, FakePreferenceStore, FakeMessageLog, FakeTransportRegistry, FakeCommunicationQueue}
     */
    private function dispatcher(
        array $events,
        bool $queueEnabled = false,
        string $fallbackLocale = 'en',
    ): array {
        $configuration = new FakeEventConfiguration($events);
        $templates = new FakeTemplateStore();
        $preferences = new FakePreferenceStore();
        $log = new FakeMessageLog();
        $transports = new FakeTransportRegistry();
        $queue = new FakeCommunicationQueue();

        $delivery = new MessageDeliveryService($log, $preferences, $transports);
        $dispatcher = new CommunicationDispatcher(
            $configuration,
            $templates,
            $preferences,
            $log,
            new TemplateRenderer(),
            $delivery,
            $queue,
            $queueEnabled,
            'en',
            $fallbackLocale,
        );

        return [$dispatcher, $templates, $preferences, $log, $transports, $queue];
    }
}

final class FakeEventConfiguration implements EventConfiguration
{
    /** @var array<string, EventDefinition> */
    private array $events = [];

    /**
     * @param list<EventDefinition> $events
     */
    public function __construct(array $events)
    {
        foreach ($events as $event) {
            $this->events[$event->key] = $event;
        }
    }

    public function find(string $eventKey): ?EventDefinition
    {
        return $this->events[$eventKey] ?? null;
    }

    public function all(): array
    {
        return array_values($this->events);
    }

    public function setChannelEnabled(string $eventKey, Channel $channel, bool $enabled): void
    {
        throw new \LogicException('Not used by this test.');
    }

    public function save(
        string $key,
        string $name,
        ?string $description,
        \Shardevcom\Communications\Domain\Enums\EventCategory $category,
        bool $active,
        array $channels,
    ): EventDefinition {
        throw new \LogicException('Not used by this test.');
    }
}

final class FakeTemplateStore implements TemplateStore
{
    /** @var array<string, MessageTemplate> */
    private array $templates = [];

    private int $nextId = 1;

    public function find(int $eventId, Channel $channel, string $locale): ?MessageTemplate
    {
        return $this->templates[$this->key($eventId, $channel, $locale)] ?? null;
    }

    public function forEvent(int $eventId, bool $activeOnly = false): array
    {
        return array_values(array_filter(
            $this->templates,
            static fn (MessageTemplate $template): bool => $template->eventId === $eventId,
        ));
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
        $template = new MessageTemplate(
            $this->nextId++,
            $eventId,
            $channel,
            $locale,
            $format,
            $subject,
            $body,
            $metadata,
        );
        $this->templates[$this->key($eventId, $channel, $locale)] = $template;

        return $template;
    }

    public function put(int $eventId, Channel $channel, string $locale, ?string $subject, string $body): void
    {
        $this->upsert($eventId, $channel, $locale, $subject, $body);
    }

    private function key(int $eventId, Channel $channel, string $locale): string
    {
        return $eventId.':'.$channel->value.':'.$locale;
    }
}

final class FakePreferenceStore implements PreferenceStore
{
    /** @var list<array{guard: string, user_id: string, channel: string, event_key: string}> */
    private array $rows = [];

    public function isOptedOut(string $guard, string $userId, Channel $channel, string $eventKey): bool
    {
        foreach ($this->rows as $row) {
            if ($row['guard'] !== $guard || $row['user_id'] !== $userId) {
                continue;
            }

            $channelMatches = $row['channel'] === self::ALL || $row['channel'] === $channel->value;
            $eventMatches = $row['event_key'] === self::ALL || $row['event_key'] === $eventKey;

            if ($channelMatches && $eventMatches) {
                return true;
            }
        }

        return false;
    }

    public function listFor(string $guard, string $userId): array
    {
        return array_values(array_map(
            static fn (array $row): array => [
                'channel' => $row['channel'],
                'event_key' => $row['event_key'],
                'reason' => null,
                'opted_out_at' => null,
            ],
            array_filter(
                $this->rows,
                static fn (array $row): bool => $row['guard'] === $guard && $row['user_id'] === $userId,
            ),
        ));
    }

    public function optOut(
        string $guard,
        string $userId,
        ?Channel $channel = null,
        ?string $eventKey = null,
        ?string $reason = null,
    ): void {
        $this->rows[] = [
            'guard' => $guard,
            'user_id' => $userId,
            'channel' => $channel?->value ?? self::ALL,
            'event_key' => $eventKey ?? self::ALL,
        ];
    }

    public function optIn(
        string $guard,
        string $userId,
        ?Channel $channel = null,
        ?string $eventKey = null,
    ): void {
        $channelKey = $channel?->value ?? self::ALL;
        $eventKey ??= self::ALL;

        $this->rows = array_values(array_filter(
            $this->rows,
            static fn (array $row): bool => ! (
                $row['guard'] === $guard
                && $row['user_id'] === $userId
                && $row['channel'] === $channelKey
                && $row['event_key'] === $eventKey
            ),
        ));
    }
}

final class FakeMessageLog implements MessageLog
{
    /** @var array<int, MessageDraft> */
    public array $drafts = [];

    /** @var array<int, MessageStatus> */
    public array $statuses = [];

    /** @var array<int, string> */
    public array $errors = [];

    private int $nextId = 1;

    public function record(MessageDraft $draft): int
    {
        $id = $this->nextId++;
        $this->drafts[$id] = $draft;
        $this->statuses[$id] = MessageStatus::QUEUED;

        return $id;
    }

    public function find(int $messageId): ?PendingMessage
    {
        $draft = $this->drafts[$messageId] ?? null;

        if ($draft === null) {
            return null;
        }

        return new PendingMessage(
            $messageId,
            (string) $messageId,
            $draft->eventKey,
            $draft->channel,
            $draft->recipientType,
            $draft->recipientId,
            $draft->recipientGuard,
            $draft->recipientAddress,
            $draft->locale,
            $draft->format,
            $draft->subject,
            $draft->body,
            $draft->payload,
            $draft->metadata,
            0,
        );
    }

    public function markSent(int $messageId, ?string $provider, ?string $providerMessageId = null): void
    {
        $this->statuses[$messageId] = MessageStatus::SENT;
    }

    public function markFailed(int $messageId, string $error): void
    {
        $this->statuses[$messageId] = MessageStatus::FAILED;
        $this->errors[$messageId] = $error;
    }

    public function markSkipped(int $messageId, MessageStatus $status, string $reason): void
    {
        $this->statuses[$messageId] = $status;
        $this->errors[$messageId] = $reason;
    }

    public function markDelivered(int $messageId): void
    {
        $this->statuses[$messageId] = MessageStatus::DELIVERED;
    }

    public function markRead(int $messageId): void
    {
        $this->statuses[$messageId] = MessageStatus::READ;
    }

    public function markProviderStatus(
        string $provider,
        string $providerMessageId,
        MessageStatus $status,
        ?string $error = null,
    ): bool {
        return false;
    }
}

final class FakeTransportRegistry implements TransportRegistry
{
    public function __construct(private readonly FakeChannelTransport $transport = new FakeChannelTransport())
    {
    }

    /**
     * @return list<OutboundMessage>
     */
    public function sent(): array
    {
        return $this->transport->sent;
    }

    public function for(Channel $channel): ?ChannelTransport
    {
        return $this->transport;
    }
}

final class FakeChannelTransport implements ChannelTransport
{
    /** @var list<OutboundMessage> */
    public array $sent = [];

    public function send(OutboundMessage $message): DeliveryResult
    {
        $this->sent[] = $message;

        return DeliveryResult::sent('fake');
    }
}

final class FakeCommunicationQueue implements CommunicationQueue
{
    /** @var list<int> */
    public array $pushed = [];

    public function push(int $messageId): void
    {
        $this->pushed[] = $messageId;
    }
}


final class FailingTransportRegistry implements TransportRegistry
{
    public function for(Channel $channel): ?ChannelTransport
    {
        return new FailingChannelTransport();
    }
}

final class FailingChannelTransport implements ChannelTransport
{
    public function send(OutboundMessage $message): DeliveryResult
    {
        return DeliveryResult::failed('smtp_down');
    }
}
