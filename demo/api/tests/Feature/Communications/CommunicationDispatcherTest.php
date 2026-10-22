<?php

declare(strict_types=1);

namespace Tests\Feature\Communications;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Shardevcom\Communications\Application\AdminNotifier;
use Shardevcom\Communications\Application\CommunicationDispatcher;
use Shardevcom\Communications\Contracts\EventConfiguration;
use Shardevcom\Communications\Contracts\PreferenceStore;
use Shardevcom\Communications\Domain\Data\Recipient;
use Shardevcom\Communications\Domain\Enums\Channel;
use Shardevcom\Communications\Domain\Enums\EventCategory;
use Shardevcom\Communications\Domain\Enums\MessageStatus;
use Shardevcom\Communications\Infrastructure\Persistence\CommunicationEvent;
use Shardevcom\Communications\Infrastructure\Persistence\CommunicationEventChannel;
use Shardevcom\Communications\Infrastructure\Persistence\CommunicationMessage;
use Shardevcom\Communications\Infrastructure\Persistence\CommunicationTemplate;
use Tests\TestCase;

final class CommunicationDispatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_the_enabled_channels_and_persists_traceability(): void
    {
        Mail::fake();

        $event = $this->event('user.registered', [Channel::EMAIL, Channel::WHATSAPP]);
        $this->template($event, Channel::EMAIL, 'Welcome {{ name }}', '<p>Hi {{ name }}</p>');
        $this->template($event, Channel::WHATSAPP, null, 'Hola {{ name }}');

        $result = $this->dispatch('user.registered', [
            'email' => 'ana@example.com',
            'whatsapp' => '+34600000000',
        ], ['name' => 'Ana']);

        $this->assertCount(2, $result->outcomes());
        $this->assertSame([MessageStatus::SENT, MessageStatus::SENT], array_map(
            static fn ($outcome): MessageStatus => $outcome->status,
            $result->outcomes(),
        ));

        $messages = CommunicationMessage::query()->orderBy('id')->get();

        $this->assertCount(2, $messages);
        $this->assertSame(MessageStatus::SENT, $messages[0]->status);
        $this->assertSame('Welcome Ana', $messages[0]->subject);
        $this->assertSame('<p>Hi Ana</p>', $messages[0]->body);
        $this->assertSame('mail', $messages[0]->provider);
        $this->assertNotNull($messages[0]->sent_at);
        $this->assertSame('Hola Ana', $messages[1]->body);
        $this->assertSame(Channel::WHATSAPP, $messages[1]->channel);
    }

    public function test_it_omits_an_unsubscribed_channel_without_sending(): void
    {
        Mail::fake();

        $event = $this->event('user.registered', [Channel::EMAIL, Channel::WHATSAPP]);
        $this->template($event, Channel::EMAIL, 'Welcome', 'Email');
        $this->template($event, Channel::WHATSAPP, null, 'WhatsApp');

        $this->app->make(PreferenceStore::class)->optOut(
            'web',
            '42',
            Channel::WHATSAPP,
            'user.registered',
            'customer request',
        );

        $this->dispatch('user.registered', [
            'email' => 'ana@example.com',
            'whatsapp' => '+34600000000',
        ]);

        $whatsapp = CommunicationMessage::query()
            ->where('channel', Channel::WHATSAPP->value)
            ->firstOrFail();

        $this->assertSame(MessageStatus::SKIPPED_UNSUBSCRIBED, $whatsapp->status);
        $this->assertSame('unsubscribed', $whatsapp->error);
        $this->assertNotNull($whatsapp->skipped_at);
        $this->assertSame(Channel::EMAIL, CommunicationMessage::query()
            ->where('status', MessageStatus::SENT->value)
            ->firstOrFail()
            ->channel);
    }

    public function test_event_channels_can_be_toggled_without_code_changes(): void
    {
        $event = $this->event('user.registered', [Channel::EMAIL]);

        $this->app->make(EventConfiguration::class)
            ->setChannelEnabled('user.registered', Channel::WHATSAPP, true);

        $this->assertDatabaseHas('communication_event_channels', [
            'event_id' => $event->id,
            'channel' => Channel::WHATSAPP->value,
            'enabled' => true,
        ]);

        $definition = $this->app->make(EventConfiguration::class)->find('user.registered');

        $this->assertNotNull($definition);
        $this->assertTrue($definition->hasChannel(Channel::EMAIL));
        $this->assertTrue($definition->hasChannel(Channel::WHATSAPP));
    }

    public function test_admin_alerts_go_to_the_in_app_panel(): void
    {
        $admin = AdminUser::factory()->create();

        $this->event(
            'admin.user.registered',
            [Channel::IN_APP],
            EventCategory::ADMIN,
        );

        $event = CommunicationEvent::query()->where('key', 'admin.user.registered')->firstOrFail();
        $this->template($event, Channel::IN_APP, 'New user', 'User {{ name }} registered');

        config([
            'communications.admin.recipients' => [[
                'id' => (string) $admin->id,
                'guard' => 'admin',
                'roles' => ['ops'],
            ]],
        ]);

        $result = $this->app->make(AdminNotifier::class)->notify(
            'admin.user.registered',
            ['name' => 'Ana'],
            ['ops'],
        );

        $this->assertSame([MessageStatus::SENT], array_map(
            static fn ($outcome): MessageStatus => $outcome->status,
            $result->outcomes(),
        ));

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => AdminUser::class,
            'notifiable_id' => $admin->id,
        ]);
    }

    /**
     * @param array<string, string> $addresses
     * @param array<string, mixed> $payload
     */
    private function dispatch(string $eventKey, array $addresses, array $payload = []): \Shardevcom\Communications\Domain\Data\DispatchResult
    {
        return $this->app->make(CommunicationDispatcher::class)->dispatch(
            $eventKey,
            Recipient::user('42', 'web', $addresses),
            $payload,
        );
    }

    /**
     * @param list<Channel> $channels
     */
    private function event(
        string $key,
        array $channels,
        EventCategory $category = EventCategory::TRANSACTIONAL,
    ): CommunicationEvent {
        $event = CommunicationEvent::query()->create([
            'key' => $key,
            'name' => $key,
            'category' => $category->value,
            'active' => true,
        ]);

        foreach ($channels as $channel) {
            CommunicationEventChannel::query()->create([
                'event_id' => $event->id,
                'channel' => $channel->value,
                'enabled' => true,
            ]);
        }

        return $event;
    }

    private function template(
        CommunicationEvent $event,
        Channel $channel,
        ?string $subject,
        string $body,
    ): CommunicationTemplate {
        return CommunicationTemplate::query()->create([
            'event_id' => $event->id,
            'channel' => $channel->value,
            'locale' => 'en',
            'format' => 'html',
            'subject' => $subject,
            'body' => $body,
            'active' => true,
        ]);
    }
}
