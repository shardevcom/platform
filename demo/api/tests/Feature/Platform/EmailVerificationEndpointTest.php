<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Email/account verification for a platform that requires it.
 *
 * The link is a Laravel signed URL, so a valid signature with the wrong email
 * hash must still be refused; resend is the authenticated recovery path.
 */
final class EmailVerificationEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_email_can_be_verified_with_a_signed_link(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('auth.email.verify.web', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->getJson($url)->assertOk()->assertJsonPath('data.verified', true);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_an_unsigned_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $this->getJson('/api/v1/auth/email/verify/'.$user->id.'/'.sha1($user->email))
            ->assertStatus(403);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_signed_link_with_the_wrong_hash_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('auth.email.verify.web', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1('someone-else@example.com'),
        ]);

        $this->getJson($url)->assertStatus(403)->assertJsonPath('code', 'INVALID_VERIFICATION_LINK');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_resend_requires_a_token(): void
    {
        $this->postJson('/api/v1/auth/email/verify/resend')->assertStatus(401);
    }

    public function test_resend_sends_a_new_link_to_the_authenticated_user(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create(['password' => 'secret-password']);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertOk()->json('data.access_token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/email/verify/resend')
            ->assertOk()
            ->assertJsonPath('data.sent', true);

        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
