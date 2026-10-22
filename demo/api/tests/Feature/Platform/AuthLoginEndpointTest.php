<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\AdminUser;
use App\Models\User;
use DateInterval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Passport;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * The first-party token login surface (showcase SPA).
 *
 * Verifies the whole exchange against the real Passport store: credentials
 * against the platform's provider, a minted personal access token, and that
 * the token it returns is actually accepted by the guarded API of that same
 * platform — a login endpoint that issued a token the API rejects would be
 * worse than no endpoint at all.
 */
final class AuthLoginEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_user_can_exchange_credentials_for_a_token(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'device_name' => 'spa',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', (string) $user->id)
            ->assertJsonStructure(['data' => ['access_token', 'expires_in', 'abilities']]);

        $token = $response->json('data.access_token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_admin_token_is_accepted_by_the_admin_api(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $admin = AdminUser::factory()->create(['password' => 'secret-password']);
        $admin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => json_encode([Hash::make('RECOVERY-ONE')]),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $challenge = $this->postJson('/admin/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'secret-password',
        ])->assertOk()
            ->assertJsonPath('data.two_factor_required', true)
            ->json('data.challenge_token');

        $token = $this->postJson('/admin/api/v1/auth/2fa/challenge', [
            'challenge_token' => $challenge,
            'code' => (new Google2FA())->getCurrentOtp($secret),
        ])->assertOk()->json('data.access_token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/admin/api/v1/products')
            ->assertOk();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');
    }

    public function test_missing_credentials_fail_validation(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['meta' => ['details' => ['email', 'password']]]);
    }

    public function test_issued_token_honours_the_personal_access_lifetime(): void
    {
        // A distinctive TTL proves the login applies Passport's PAT lifetime
        // (fed from config/passport.php) instead of an implicit/unlimited value.
        Passport::personalAccessTokensExpireIn(new DateInterval('PT2H'));

        $user = User::factory()->create(['password' => 'secret-password']);

        $expiresIn = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertOk()->json('data.expires_in');

        $this->assertIsInt($expiresIn);
        $this->assertEqualsWithDelta(
            7200,
            $expiresIn,
            10,
            'The issued token must expire per Passport::personalAccessTokensExpireIn().'
        );
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertOk()->json('data.access_token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_me_requires_a_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }
}
