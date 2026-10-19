<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Shardevcom\Security\Infrastructure\AccountLock;
use Tests\TestCase;

/**
 * Individual account disable/reactivate for the `web` platform.
 *
 * A disabled account cannot log in and its live tokens are refused, whether the
 * disable was self-service or applied by another actor.
 */
final class AccountLockEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user, string $password = 'password'): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ])->assertOk()->json('data.access_token');
    }

    public function test_a_user_can_disable_their_own_account(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/account/disable', ['current_password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.disabled', true);

        $this->assertTrue(app(AccountLock::class)->isDisabled('web', $user->id));
    }

    public function test_a_disabled_account_cannot_log_in(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/account/disable', ['current_password' => 'password'])
            ->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(403)->assertJsonPath('code', 'ACCOUNT_DISABLED');
    }

    public function test_disabling_requires_the_current_password(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/account/disable', ['current_password' => 'not-it'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_CURRENT_PASSWORD');

        $this->assertFalse(app(AccountLock::class)->isDisabled('web', $user->id));
    }

    public function test_disabling_revokes_existing_tokens(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/account/disable', ['current_password' => 'password'])
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_a_live_token_is_rejected_once_the_account_is_disabled(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        // Simulates another actor disabling the account without revoking tokens.
        app(AccountLock::class)->disable('web', $user->id, 'policy');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(403)
            ->assertJsonPath('code', 'ACCOUNT_DISABLED');
    }

    public function test_reactivating_with_valid_credentials_restores_access(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/account/disable', ['current_password' => 'password'])
            ->assertOk();

        $this->postJson('/api/v1/auth/account/reactivate', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('data.disabled', false);

        $this->assertFalse(app(AccountLock::class)->isDisabled('web', $user->id));
        $this->tokenFor($user);
    }

    public function test_reactivating_with_invalid_credentials_is_rejected(): void
    {
        $user = User::factory()->create();
        app(AccountLock::class)->disable('web', $user->id);

        $this->postJson('/api/v1/auth/account/reactivate', [
            'email' => $user->email,
            'password' => 'wrong',
        ])->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');

        $this->assertTrue(app(AccountLock::class)->isDisabled('web', $user->id));
    }
}
