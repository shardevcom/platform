<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Account notifications: list, unread count, read/read-all/delete, and that a
 * user can only touch their own.
 */
final class NotificationsEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->json('data.access_token');
    }

    private function notify(User $user, string $title, bool $read = false): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\Welcome',
            'data' => ['title' => $title],
            'read_at' => $read ? now() : null,
        ]);
    }

    public function test_notifications_are_listed_with_an_unread_count(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->notify($user, 'First');
        $this->notify($user, 'Second');
        $this->notify($user, 'Old', read: true);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/notifications')
            ->assertOk()
            ->assertJsonCount(3, 'data.items')
            ->assertJsonPath('data.unread_count', 2)
            ->assertJsonStructure(['data' => ['items' => [['id', 'type', 'data', 'read_at', 'created_at']]]]);
    }

    public function test_a_notification_can_be_marked_as_read(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $notification = $this->notify($user, 'Unread');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/notifications/'.$notification->id.'/read')
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id);

        $this->assertNotNull($notification->fresh()->read_at);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_all_notifications_can_be_marked_as_read(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->notify($user, 'One');
        $this->notify($user, 'Two');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_a_notification_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $notification = $this->notify($user, 'Gone');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/auth/notifications/'.$notification->id)
            ->assertOk();

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_a_user_cannot_touch_another_accounts_notification(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $notification = $this->notify($owner, 'Private');

        $token = $this->tokenFor($intruder);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/notifications/'.$notification->id.'/read')
            ->assertStatus(404)
            ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/auth/notifications/'.$notification->id)
            ->assertStatus(404);

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_the_notifications_surface_requires_a_token(): void
    {
        $this->getJson('/api/v1/auth/notifications')->assertStatus(401);
    }
}
