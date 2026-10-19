<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * The first-party password recovery surface (forgot / reset) for every platform.
 *
 * Exercises the real password brokers configured per platform: a reset link is
 * emailed, the token is exchanged for a new password, and the platform's own
 * login then accepts that password. Also locks the anti-enumeration contract:
 * an unknown email must answer exactly like a known one.
 */
final class PasswordResetEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_a_reset_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_does_not_reveal_whether_the_email_exists(): void
    {
        Notification::fake();

        $known = User::factory()->create();

        $knownResponse = $this->postJson('/api/v1/auth/forgot-password', ['email' => $known->email]);
        $unknownResponse = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com']);

        $knownResponse->assertOk();
        $unknownResponse->assertOk();
        $this->assertSame($knownResponse->json('message'), $unknownResponse->json('message'));
    }

    public function test_forgot_password_requires_an_email(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['meta' => ['details' => ['email']]]);
    }

    public function test_reset_password_with_a_valid_token_changes_the_password(): void
    {
        $user = User::factory()->create(['password' => 'old-secret-password']);
        $token = Password::broker('web')->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
            'token' => $token,
        ])->assertOk()->assertJsonPath('status', 'success');

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'new-secret-password',
        ])->assertOk();
    }

    public function test_reset_password_uses_the_platform_broker(): void
    {
        $admin = AdminUser::factory()->create(['password' => 'old-secret-password']);
        $token = Password::broker('admin')->createToken($admin);

        $this->postJson('/admin/api/v1/auth/reset-password', [
            'email' => $admin->email,
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
            'token' => $token,
        ])->assertOk();

        $this->postJson('/admin/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'new-secret-password',
        ])->assertOk();
    }

    public function test_reset_password_rejects_an_invalid_token(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
            'token' => 'not-a-real-token',
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_RESET_TOKEN');
    }

    public function test_reset_password_validates_the_confirmation(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'password' => 'new-secret-password',
            'password_confirmation' => 'different-password',
            'token' => 'whatever',
        ])->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['meta' => ['details' => ['password_confirmation']]]);
    }
}
