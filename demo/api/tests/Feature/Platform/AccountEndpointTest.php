<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Account surface for the authenticated user: profile, password, preferences.
 *
 * Each platform serves its own copy of these routes; these tests exercise the
 * `web` platform (users table, verification enabled).
 */
final class AccountEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user, string $password = 'password'): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ])->assertOk()->json('data.access_token');
    }

    public function test_the_profile_name_can_be_updated(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/auth/profile', ['name' => 'Renamed User'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed User');

        $this->assertSame('Renamed User', $user->fresh()->name);
    }

    public function test_changing_the_email_unverifies_it_and_resends_the_link(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $this->tokenFor($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/auth/profile', ['email' => 'new-address@example.com'])
            ->assertOk()
            ->assertJsonPath('data.email', 'new-address@example.com')
            ->assertJsonPath('data.email_verified', false);

        $fresh = $user->fresh();
        $this->assertSame('new-address@example.com', $fresh->email);
        $this->assertNull($fresh->email_verified_at);

        Notification::assertSentTo($fresh, VerifyEmail::class);
    }

    public function test_an_email_already_used_by_another_account_is_rejected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $token = $this->tokenFor($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/auth/profile', ['email' => $other->email])
            ->assertStatus(422)
            ->assertJsonPath('meta.details.email.0', 'The email has already been taken.');

        $this->assertNotSame($other->email, $user->fresh()->email);
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        $token = $this->tokenFor($user, 'secret-password');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'not-it',
                'password' => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_CURRENT_PASSWORD');
    }

    public function test_the_password_can_be_changed_and_the_new_one_works(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        $token = $this->tokenFor($user, 'secret-password');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'secret-password',
                'password' => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ])
            ->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'brand-new-password',
        ])->assertOk();
    }

    public function test_preferences_default_before_anything_is_saved(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/preferences')
            ->assertOk()
            ->assertJsonPath('data.theme', 'system')
            ->assertJsonPath('data.language', 'en')
            ->assertJsonPath('data.notifications.email', true)
            ->assertJsonPath('data.notifications.push', false);
    }

    public function test_preferences_are_merged_and_persisted(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/auth/preferences', [
                'theme' => 'dark',
                'notifications' => ['push' => true],
            ])
            ->assertOk()
            ->assertJsonPath('data.theme', 'dark')
            ->assertJsonPath('data.notifications.email', true)
            ->assertJsonPath('data.notifications.push', true);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/preferences')
            ->assertOk()
            ->assertJsonPath('data.theme', 'dark')
            ->assertJsonPath('data.language', 'en')
            ->assertJsonPath('data.notifications.push', true);
    }

    public function test_an_invalid_theme_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/auth/preferences', ['theme' => 'neon'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED');
    }
}
