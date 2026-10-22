<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\AdminUser;
use App\Models\EmployeeUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * T-CASA-009: privileged guards cannot obtain a Passport token from the
 * password step alone.
 */
final class TwoFactorLoginEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const RECOVERY_CODE = 'RECOVERY-ONE';

    public function test_admin_without_two_factor_gets_an_enrollment_challenge_not_a_session(): void
    {
        $admin = AdminUser::factory()->create(['password' => 'secret-password']);

        $response = $this->postJson('/admin/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'secret-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.two_factor_enrollment_required', true)
            ->assertJsonPath('data.methods.0', 'totp')
            ->assertJsonStructure(['data' => ['enrollment_token', 'expires_in']]);

        $this->assertNull($response->json('data.access_token'));
        $this->assertArrayNotHasKey('access_token', $response->json('data'));

        $this->getJson('/admin/api/v1/products')->assertStatus(401);
    }

    public function test_enrollment_confirmation_issues_the_first_privileged_token(): void
    {
        $admin = AdminUser::factory()->create(['password' => 'secret-password']);
        $enrollment = $this->postJson('/admin/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'secret-password',
        ])->assertOk()->json('data.enrollment_token');

        $setup = $this->postJson('/admin/api/v1/auth/2fa/setup', [
            'enrollment_token' => $enrollment,
        ])->assertOk()
            ->assertJsonStructure(['data' => ['secret', 'otpauth_url', 'qr_code', 'recovery_codes']]);

        $secret = $setup->json('data.secret');
        $codes = $setup->json('data.recovery_codes');

        $this->assertCount(8, $codes);

        $stored = $admin->fresh();
        $this->assertSame($secret, $stored->two_factor_secret);
        $this->assertStringNotContainsString($codes[0], (string) $stored->two_factor_recovery_codes);

        $code = (new Google2FA())->getCurrentOtp($secret);

        $confirmed = $this->postJson('/admin/api/v1/auth/2fa/setup/confirm', [
            'enrollment_token' => $enrollment,
            'code' => $code,
            'device_name' => 'test',
        ])->assertOk()
            ->assertJsonPath('data.user.guard', 'admin')
            ->assertJsonPath('data.token_type', 'Bearer');

        $token = $confirmed->json('data.access_token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/admin/api/v1/products')
            ->assertOk();
    }

    public function test_confirmed_two_factor_login_requires_a_valid_challenge_code(): void
    {
        $admin = $this->adminWithTwoFactor();
        $challenge = $this->startChallenge($admin);

        $this->assertNull($challenge['access_token'] ?? null);
        $this->postJson('/admin/api/v1/auth/2fa/challenge', [
            'challenge_token' => $challenge['challenge_token'],
            'code' => '000000',
        ])->assertStatus(422)->assertJsonPath('code', 'TWO_FACTOR_FAILED');

        $verified = $this->postJson('/admin/api/v1/auth/2fa/challenge', [
            'challenge_token' => $challenge['challenge_token'],
            'code' => (new Google2FA())->getCurrentOtp(self::secret()),
            'device_name' => 'test',
        ])->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$verified->json('data.access_token'))
            ->getJson('/admin/api/v1/products')
            ->assertOk();
    }

    public function test_recovery_code_is_consumed_on_first_use(): void
    {
        $admin = $this->adminWithTwoFactor();

        $first = $this->startChallenge($admin);

        $this->postJson('/admin/api/v1/auth/2fa/challenge', [
            'challenge_token' => $first['challenge_token'],
            'code' => self::RECOVERY_CODE,
            'type' => 'recovery_code',
        ])->assertOk();

        $second = $this->startChallenge($admin);

        $this->postJson('/admin/api/v1/auth/2fa/challenge', [
            'challenge_token' => $second['challenge_token'],
            'code' => self::RECOVERY_CODE,
            'type' => 'recovery_code',
        ])->assertStatus(422)->assertJsonPath('code', 'TWO_FACTOR_FAILED');

        $remaining = json_decode((string) $admin->fresh()->two_factor_recovery_codes, true);
        $this->assertCount(0, $remaining);
    }

    public function test_employee_is_also_a_required_guard(): void
    {
        $employee = EmployeeUser::factory()->create(['password' => 'secret-password']);

        $this->postJson('/employee/api/v1/auth/login', [
            'email' => $employee->email,
            'password' => 'secret-password',
        ])->assertOk()->assertJsonPath('data.two_factor_enrollment_required', true);
    }

    public function test_remember_does_not_bypass_the_second_factor(): void
    {
        $admin = $this->adminWithTwoFactor();

        $response = $this->postJson('/admin/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'secret-password',
            'remember' => true,
        ]);

        $response->assertOk()->assertJsonPath('data.two_factor_required', true);
        $this->assertArrayNotHasKey('access_token', $response->json('data'));
    }

    private function adminWithTwoFactor(): AdminUser
    {
        $admin = AdminUser::factory()->create(['password' => 'secret-password']);
        $admin->forceFill([
            'two_factor_secret' => self::secret(),
            'two_factor_recovery_codes' => json_encode([Hash::make(self::RECOVERY_CODE)]),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $admin;
    }

    /**
     * @return array<string, mixed>
     */
    private function startChallenge(AdminUser $admin): array
    {
        return $this->postJson('/admin/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'secret-password',
        ])->assertOk()
            ->assertJsonPath('data.two_factor_required', true)
            ->json('data');
    }

    private static function secret(): string
    {
        return 'JBSWY3DPEHPK3PXP';
    }
}
