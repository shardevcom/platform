<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Shardevcom\Security\Notifications\InvitationNotification;
use Tests\TestCase;

/**
 * Invitations for the `web` platform: a member invites an email, the invitee
 * inspects the token and accepts it to create a verified account.
 */
final class InvitationEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->json('data.access_token');
    }

    private function invite(string $email, ?User $inviter = null): string
    {
        $inviter ??= User::factory()->create();
        $token = $this->tokenFor($inviter);

        Notification::fake();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/invitations', ['email' => $email])
            ->assertCreated()
            ->assertJsonPath('data.email', $email);

        $plain = null;

        Notification::assertSentOnDemand(
            InvitationNotification::class,
            function (InvitationNotification $notification) use (&$plain): bool {
                parse_str((string) parse_url($notification->url, PHP_URL_QUERY), $query);
                $plain = $query['token'] ?? null;

                return true;
            }
        );

        $this->assertIsString($plain);

        return $plain;
    }

    public function test_a_member_can_invite_an_email(): void
    {
        $inviter = User::factory()->create();
        $token = $this->tokenFor($inviter);

        Notification::fake();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/invitations', ['email' => 'guest@example.com'])
            ->assertCreated()
            ->assertJsonPath('data.email', 'guest@example.com')
            ->assertJsonStructure(['data' => ['email', 'expires_at']]);

        $this->assertDatabaseHas('platform_invitations', ['guard' => 'web', 'email' => 'guest@example.com']);
        Notification::assertSentOnDemand(InvitationNotification::class);
    }

    public function test_a_registered_email_cannot_be_invited(): void
    {
        $inviter = User::factory()->create();
        $existing = User::factory()->create();
        $token = $this->tokenFor($inviter);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/invitations', ['email' => $existing->email])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED');
    }

    public function test_an_invitation_can_be_inspected_with_its_token(): void
    {
        $plain = $this->invite('guest@example.com');

        $this->getJson('/api/v1/auth/invitations/'.$plain)
            ->assertOk()
            ->assertJsonPath('data.email', 'guest@example.com')
            ->assertJsonPath('data.guard', 'web')
            ->assertJsonPath('data.valid', true);
    }

    public function test_an_unknown_token_is_not_valid(): void
    {
        $this->getJson('/api/v1/auth/invitations/not-a-real-token')
            ->assertStatus(404)
            ->assertJsonPath('code', 'INVALID_INVITATION');
    }

    public function test_an_invitation_can_be_accepted_creating_a_verified_account(): void
    {
        $plain = $this->invite('newbie@example.com');

        $this->postJson('/api/v1/auth/invitations/accept', [
            'token' => $plain,
            'name' => 'Newbie',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'newbie@example.com')
            ->assertJsonPath('data.user.email_verified', true);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'newbie@example.com',
            'password' => 'secret-password',
        ])->assertOk();

        $this->assertDatabaseMissing('platform_invitations', [
            'email' => 'newbie@example.com',
            'accepted_at' => null,
        ]);
    }

    public function test_an_accepted_invitation_cannot_be_reused(): void
    {
        $plain = $this->invite('newbie@example.com');

        $this->postJson('/api/v1/auth/invitations/accept', [
            'token' => $plain,
            'name' => 'Newbie',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertCreated();

        $this->postJson('/api/v1/auth/invitations/accept', [
            'token' => $plain,
            'name' => 'Impostor',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertStatus(404)->assertJsonPath('code', 'INVALID_INVITATION');
    }

    public function test_accepting_requires_a_token(): void
    {
        $this->assertDatabaseCount('platform_invitations', 0);

        $this->postJson('/api/v1/auth/invitations/accept', [
            'name' => 'Newbie',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertStatus(404)->assertJsonPath('code', 'INVALID_INVITATION');
    }
}
