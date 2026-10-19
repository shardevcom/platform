<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Shardevcom\Security\Infrastructure\AccountLock;
use Shardevcom\Security\Infrastructure\PlatformOtpCode;
use Shardevcom\Security\Notifications\OtpNotification;
use Tests\TestCase;

/**
 * Passwordless OTP login for the `web` platform.
 */
final class OtpEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function requestCode(User $user): string
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/otp/request', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('data.sent', true);

        $code = null;

        Notification::assertSentOnDemand(
            OtpNotification::class,
            function (OtpNotification $notification) use (&$code): bool {
                $code = $notification->code;

                return true;
            }
        );

        $this->assertIsString($code);

        return $code;
    }

    public function test_the_request_is_opaque_and_sends_a_code_for_a_known_account(): void
    {
        $user = User::factory()->create();
        $code = $this->requestCode($user);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertDatabaseHas('platform_otp_codes', ['guard' => 'web', 'email' => $user->email]);
    }

    public function test_an_unknown_email_gets_the_same_answer_without_sending(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/otp/request', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJsonPath('data.sent', true);

        Notification::assertNothingSent();
        $this->assertDatabaseCount('platform_otp_codes', 0);
    }

    public function test_a_valid_code_authenticates(): void
    {
        $user = User::factory()->create();
        $code = $this->requestCode($user);

        $this->postJson('/api/v1/auth/otp/verify', ['email' => $user->email, 'code' => $code])
            ->assertOk()
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonStructure(['data' => ['access_token', 'user' => ['id', 'email']]]);
    }

    public function test_a_wrong_code_is_refused(): void
    {
        $user = User::factory()->create();
        $this->requestCode($user);

        $this->postJson('/api/v1/auth/otp/verify', ['email' => $user->email, 'code' => '000000'])
            ->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_OTP');
    }

    public function test_an_expired_code_is_refused(): void
    {
        $user = User::factory()->create();
        $code = $this->requestCode($user);

        PlatformOtpCode::query()->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/v1/auth/otp/verify', ['email' => $user->email, 'code' => $code])
            ->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_OTP');
    }

    public function test_a_code_is_single_use(): void
    {
        $user = User::factory()->create();
        $code = $this->requestCode($user);

        $this->postJson('/api/v1/auth/otp/verify', ['email' => $user->email, 'code' => $code])->assertOk();

        $this->postJson('/api/v1/auth/otp/verify', ['email' => $user->email, 'code' => $code])
            ->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_OTP');
    }

    public function test_a_disabled_account_cannot_sign_in_with_a_code(): void
    {
        $user = User::factory()->create();
        $code = $this->requestCode($user);

        app(AccountLock::class)->disable('web', $user->id);

        $this->postJson('/api/v1/auth/otp/verify', ['email' => $user->email, 'code' => $code])
            ->assertStatus(403)
            ->assertJsonPath('code', 'ACCOUNT_DISABLED');
    }

    public function test_too_many_wrong_attempts_consume_the_code(): void
    {
        config(['security.otp.max_attempts' => 2]);

        $user = User::factory()->create();
        $code = $this->requestCode($user);

        $this->postJson('/api/v1/auth/otp/verify', ['email' => $user->email, 'code' => '000000'])->assertStatus(401);
        $this->postJson('/api/v1/auth/otp/verify', ['email' => $user->email, 'code' => '000001'])->assertStatus(401);

        $this->postJson('/api/v1/auth/otp/verify', ['email' => $user->email, 'code' => $code])
            ->assertStatus(429)
            ->assertJsonPath('code', 'TOO_MANY_ATTEMPTS');
    }
}
