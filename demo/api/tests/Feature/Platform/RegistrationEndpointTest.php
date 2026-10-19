<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * First-party self-registration, gated by the platform's `public` flag.
 *
 * A public platform creates the account through its own model and issues a
 * token identical to login's; a private platform refuses (its landing is the
 * login, not a sign-up form). When verification is required the account starts
 * unverified and a verification email goes out.
 */
final class RegistrationEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_public_platform_allows_registration_and_returns_a_token(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'New User',
            'email' => 'new-user@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user.email', 'new-user@example.com')
            ->assertJsonStructure(['data' => ['access_token', 'abilities', 'user' => ['email_verified']]]);

        $this->withHeader('Authorization', 'Bearer '.$response->json('data.access_token'))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'new-user@example.com');
    }

    public function test_registration_is_disabled_on_a_private_platform(): void
    {
        $this->postJson('/admin/api/v1/auth/register', [
            'name' => 'New Admin',
            'email' => 'new-admin@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertStatus(403)->assertJsonPath('code', 'REGISTRATION_DISABLED');
    }

    public function test_registration_validates_its_input(): void
    {
        $this->postJson('/api/v1/auth/register', [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['meta' => ['details' => ['name', 'email', 'password']]]);
    }

    public function test_the_email_must_be_unique(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Duplicated',
            'email' => $user->email,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['meta' => ['details' => ['email']]]);
    }

    public function test_registration_sends_a_verification_email_when_required(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Needs Verification',
            'email' => 'needs-verification@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertCreated();

        $user = User::where('email', 'needs-verification@example.com')->firstOrFail();

        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
