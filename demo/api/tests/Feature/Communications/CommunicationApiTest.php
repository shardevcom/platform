<?php

declare(strict_types=1);

namespace Tests\Feature\Communications;

use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Shardevcom\Communications\Contracts\AdminRecipientResolver;
use Shardevcom\Communications\Domain\Enums\Channel;
use Shardevcom\Communications\Domain\Enums\MessageStatus;
use Shardevcom\Communications\Infrastructure\Persistence\CommunicationEvent;
use Shardevcom\Communications\Infrastructure\Persistence\CommunicationEventChannel;
use Shardevcom\Communications\Infrastructure\Persistence\CommunicationMessage;
use Tests\TestCase;

final class CommunicationApiTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_ABILITIES = ['communications.view', 'communications.update', 'communications.send'];

    public function test_admin_can_list_events_and_replace_the_channel_matrix(): void
    {
        $event = $this->event('user.registered', [Channel::EMAIL]);
        $this->adminToken();

        $this->getJson('/admin/api/v1/communications/admin/events')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'user.registered')
            ->assertJsonPath('data.0.channels.0', 'email');

        $this->putJson('/admin/api/v1/communications/admin/events/'.$event->key.'/channels', [
            'channels' => ['email', 'in_app'],
        ])
            ->assertOk()
            ->assertJsonPath('data.channels', ['email', 'in_app']);

        $this->assertDatabaseHas('communication_event_channels', [
            'event_id' => $event->id,
            'channel' => 'in_app',
            'enabled' => true,
        ]);

        $this->putJson('/admin/api/v1/communications/admin/events/'.$event->key.'/channels', [
            'channels' => ['email', 'telegram'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED');
    }

    public function test_a_view_only_token_cannot_change_the_channel_matrix(): void
    {
        $event = $this->event('user.registered', [Channel::EMAIL]);
        $admin = AdminUser::factory()->create();
        $this->withHeader('Authorization', 'Bearer '.$admin->createToken('cli', ['communications.view'])->accessToken);

        $this->getJson('/admin/api/v1/communications/admin/events')->assertOk();

        $this->putJson('/admin/api/v1/communications/admin/events/'.$event->key.'/channels', [
            'channels' => ['email', 'in_app'],
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_admin_can_upsert_and_list_templates(): void
    {
        $event = $this->event('user.registered', [Channel::EMAIL]);
        $this->adminToken();

        $this->putJson('/admin/api/v1/communications/admin/events/'.$event->key.'/templates', [
            'channel' => 'email',
            'locale' => 'es',
            'subject' => 'Bienvenido',
            'body' => '<p>Hola {{ name }}</p>',
            'format' => 'html',
            'metadata' => ['category' => 'welcome'],
        ])
            ->assertOk()
            ->assertJsonPath('data.locale', 'es')
            ->assertJsonPath('data.subject', 'Bienvenido');

        $this->getJson('/admin/api/v1/communications/admin/events/'.$event->key.'/templates')
            ->assertOk()
            ->assertJsonPath('data.event', 'user.registered')
            ->assertJsonPath('data.templates.0.channel', 'email')
            ->assertJsonPath('data.templates.0.metadata.category', 'welcome');
    }

    public function test_admin_can_filter_the_message_history(): void
    {
        $this->message('user.registered', Channel::EMAIL, MessageStatus::SENT);
        $this->message('user.registered', Channel::WHATSAPP, MessageStatus::FAILED);
        $this->adminToken();

        $this->getJson('/admin/api/v1/communications/admin/messages?channel=email&status=sent')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.channel', 'email')
            ->assertJsonPath('meta.pagination.total', 1);

        $this->getJson('/admin/api/v1/communications/admin/messages?status=unknown')
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED');
    }

    public function test_an_authenticated_user_can_manage_their_own_opt_out(): void
    {
        $user = User::factory()->create();
        $this->withHeader('Authorization', 'Bearer '.$user->createToken('cli')->accessToken);

        $this->postJson('/api/v1/communications/preferences/opt-out', [
            'channel' => 'whatsapp',
            'event_key' => 'user.registered',
            'reason' => 'Too many messages',
        ])->assertOk();

        $this->getJson('/api/v1/communications/preferences')
            ->assertOk()
            ->assertJsonPath('data.0.channel', 'whatsapp')
            ->assertJsonPath('data.0.event_key', 'user.registered');

        $this->postJson('/api/v1/communications/preferences/opt-in', [
            'channel' => 'whatsapp',
            'event_key' => 'user.registered',
        ])->assertOk();

        $this->getJson('/api/v1/communications/preferences')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_an_administrative_alert_is_dispatched_through_the_configured_matrix(): void
    {
        $admin = AdminUser::factory()->create();
        $event = $this->event('admin.user.registered', [Channel::IN_APP], 'admin');
        $this->withHeader('Authorization', 'Bearer '.$admin->createToken('cli', self::ADMIN_ABILITIES)->accessToken);

        \Shardevcom\Communications\Infrastructure\Persistence\CommunicationTemplate::query()->create([
            'event_id' => $event->id,
            'channel' => 'in_app',
            'locale' => 'en',
            'format' => 'text',
            'subject' => 'New user',
            'body' => 'User {{ name }} registered',
            'active' => true,
        ]);

        config([
            'communications.admin.recipients' => [[
                'id' => (string) $admin->id,
                'guard' => 'admin',
                'roles' => ['ops'],
            ]],
        ]);

        $this->postJson('/admin/api/v1/communications/admin/alerts', [
            'event_key' => 'admin.user.registered',
            'payload' => ['name' => 'Ana'],
            'roles' => ['ops'],
        ])
            ->assertOk()
            ->assertJsonPath('data.event', 'admin.user.registered')
            ->assertJsonPath('data.outcomes.0.status', 'sent');

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => AdminUser::class,
            'notifiable_id' => $admin->id,
        ]);
    }

    public function test_registration_dispatches_the_configured_registered_event(): void
    {
        Mail::fake();

        $event = $this->event('user.registered', [Channel::EMAIL]);
        \Shardevcom\Communications\Infrastructure\Persistence\CommunicationTemplate::query()->create([
            'event_id' => $event->id,
            'channel' => 'email',
            'locale' => 'en',
            'format' => 'html',
            'subject' => 'Welcome',
            'body' => 'Welcome {{ name }}',
            'active' => true,
        ]);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertCreated();

        $this->assertDatabaseHas('communication_messages', [
            'event_key' => 'user.registered',
            'channel' => 'email',
            'recipient_guard' => 'web',
            'recipient_address' => 'ana@example.com',
            'status' => 'sent',
        ]);
    }

    public function test_the_database_admin_resolver_queries_the_configured_role_column(): void
    {
        $admin = AdminUser::factory()->create(['email' => 'ops@example.com']);

        config([
            'communications.admin.resolver' => 'database',
            'communications.admin.database' => [
                'guard' => 'admin',
                'model' => AdminUser::class,
                'role_column' => 'email',
                'email_column' => 'email',
            ],
        ]);

        $recipients = $this->app->make(AdminRecipientResolver::class)->resolve(['ops@example.com']);

        $this->assertCount(1, $recipients);
        $this->assertSame((string) $admin->id, $recipients[0]->id);
        $this->assertSame('admin', $recipients[0]->guard);
        $this->assertSame('ops@example.com', $recipients[0]->addresses['email']);
        $this->assertSame(['ops@example.com'], $recipients[0]->roles);
    }

    public function test_a_signed_webhook_updates_the_delivery_status(): void
    {
        $message = $this->message('user.registered', Channel::SMS, MessageStatus::SENT, 'twilio', 'SM-1');
        config(['communications.webhooks.secret' => 'secret']);

        $payload = json_encode(['message_id' => 'SM-1', 'status' => 'delivered'], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $payload, 'secret');

        $this->call(
            'POST',
            '/api/v1/communications/webhooks/twilio',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Communications-Signature' => 'sha256='.$signature,
            ],
            $payload,
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered');

        $this->assertSame(MessageStatus::DELIVERED, $message->fresh()->status);
        $this->assertNotNull($message->fresh()->delivered_at);

        $this->call(
            'POST',
            '/api/v1/communications/webhooks/twilio',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Communications-Signature' => 'sha256=wrong',
            ],
            $payload,
        )->assertStatus(401);
    }

    public function test_twilio_status_callbacks_are_verified_with_the_native_signature(): void
    {
        $message = $this->message('user.registered', Channel::SMS, MessageStatus::SENT, 'twilio', 'SM-1');

        config([
            'communications.webhooks.providers.twilio.driver' => 'twilio',
            'communications.webhooks.providers.twilio.auth_token' => 'twilio-token',
        ]);

        $params = ['MessageSid' => 'SM-1', 'MessageStatus' => 'delivered'];
        ksort($params);
        $data = url('/api/v1/communications/webhooks/twilio');

        foreach ($params as $key => $value) {
            $data .= $key.$value;
        }

        $signature = base64_encode(hash_hmac('sha1', $data, 'twilio-token', true));

        $this->post('/api/v1/communications/webhooks/twilio', $params, ['X-Twilio-Signature' => $signature])
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered');

        $this->assertSame(MessageStatus::DELIVERED, $message->fresh()->status);
    }

    public function test_meta_whatsapp_nested_status_payloads_are_normalized(): void
    {
        $message = $this->message('user.registered', Channel::WHATSAPP, MessageStatus::SENT, 'meta_whatsapp', 'wamid.1');
        config(['communications.webhooks.secret' => 'secret']);

        $payload = json_encode([
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [[
                            'id' => 'wamid.1',
                            'status' => 'read',
                        ]],
                    ],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $payload, 'secret');

        $this->call(
            'POST',
            '/api/v1/communications/webhooks/meta_whatsapp',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Communications-Signature' => 'sha256='.$signature,
            ],
            $payload,
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'read');

        $this->assertSame(MessageStatus::READ, $message->fresh()->status);
        $this->assertNotNull($message->fresh()->read_at);
    }

    public function test_the_sync_command_bootstraps_events_and_templates_from_config(): void
    {
        config([
            'communications.events' => [
                'user.registered' => [
                    'name' => 'User registered',
                    'category' => 'transactional',
                    'channels' => ['email'],
                    'templates' => [
                        'email' => [
                            'en' => ['subject' => 'Welcome', 'body' => '<p>Welcome {{ name }}</p>'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->artisan('communications:sync')->assertSuccessful();

        $this->assertDatabaseHas('communication_events', ['key' => 'user.registered']);
        $this->assertDatabaseHas('communication_templates', [
            'channel' => 'email',
            'locale' => 'en',
            'subject' => 'Welcome',
        ]);
    }

    private function adminToken(): void
    {
        $this->withHeader(
            'Authorization',
            'Bearer '.AdminUser::factory()->create()->createToken('cli', self::ADMIN_ABILITIES)->accessToken,
        );
    }

    /**
     * @param list<Channel> $channels
     */
    private function event(string $key, array $channels, string $category = 'transactional'): CommunicationEvent
    {
        $event = CommunicationEvent::query()->create([
            'key' => $key,
            'name' => $key,
            'category' => $category,
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

    private function message(
        string $eventKey,
        Channel $channel,
        MessageStatus $status,
        ?string $provider = null,
        ?string $providerMessageId = null,
    ): CommunicationMessage {
        return CommunicationMessage::query()->create([
            'uuid' => (string) Str::uuid(),
            'event_key' => $eventKey,
            'channel' => $channel->value,
            'recipient_type' => 'user',
            'recipient_id' => '1',
            'recipient_guard' => 'web',
            'recipient_address' => 'ana@example.com',
            'locale' => 'en',
            'format' => 'html',
            'status' => $status->value,
            'subject' => 'Subject',
            'body' => '<p>Body</p>',
            'provider' => $provider,
            'provider_message_id' => $providerMessageId,
            'attempts' => 1,
            'queued_at' => now(),
        ]);
    }
}
