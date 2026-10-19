<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\Support\WebAuthnAuthenticator;
use Tests\TestCase;

/**
 * WebAuthn / passkey ceremonies for the `web` platform, driven by a software
 * authenticator (see Tests\Support\WebAuthnAuthenticator).
 */
final class WebAuthnEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const RP_ID = 'localhost';

    private const ORIGIN = 'http://localhost';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'security.webauthn.rp_id' => self::RP_ID,
            'security.webauthn.allowed_origins' => [self::ORIGIN],
        ]);
    }

    private function tokenFor(User $user): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->json('data.access_token');
    }

    private function registerPasskey(User $user, WebAuthnAuthenticator $authenticator): string
    {
        $token = $this->tokenFor($user);

        $options = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/webauthn/register/options')
            ->assertOk()
            ->json('data.publicKey');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/webauthn/register', $authenticator->registration(
                self::RP_ID,
                self::ORIGIN,
                $options['challenge'],
            ))
            ->assertCreated();

        return (string) $options['user']['id'];
    }

    public function test_registration_options_carry_the_relying_party_and_a_challenge(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/webauthn/register/options')
            ->assertOk()
            ->assertJsonPath('data.publicKey.rp.id', self::RP_ID)
            ->assertJsonPath('data.publicKey.attestation', 'none')
            ->assertJsonStructure(['data' => ['publicKey' => ['challenge', 'user' => ['id', 'name', 'displayName'], 'pubKeyCredParams']]]);
    }

    public function test_a_passkey_can_be_registered_and_used_to_log_in(): void
    {
        $user = User::factory()->create();
        $authenticator = new WebAuthnAuthenticator();

        $handle = $this->registerPasskey($user, $authenticator);

        $this->assertDatabaseCount('platform_webauthn_credentials', 1);

        $loginOptions = $this->postJson('/api/v1/auth/webauthn/login/options', ['email' => $user->email])
            ->assertOk()
            ->assertJsonCount(1, 'data.publicKey.allowCredentials')
            ->json('data.publicKey');

        $this->postJson('/api/v1/auth/webauthn/login', $authenticator->assertion(
            self::RP_ID,
            self::ORIGIN,
            $loginOptions['challenge'],
            1,
            base64_encode($handle),
        ))
            ->assertOk()
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonStructure(['data' => ['access_token', 'user' => ['id', 'email']]]);
    }

    public function test_login_options_for_an_unknown_email_stay_opaque(): void
    {
        $this->postJson('/api/v1/auth/webauthn/login/options', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJsonCount(0, 'data.publicKey.allowCredentials')
            ->assertJsonStructure(['data' => ['publicKey' => ['challenge', 'rpId']]]);
    }

    public function test_an_unknown_challenge_is_rejected(): void
    {
        $authenticator = new WebAuthnAuthenticator();

        $this->postJson('/api/v1/auth/webauthn/login', $authenticator->assertion(
            self::RP_ID,
            self::ORIGIN,
            Base64UrlSafe::encodeUnpadded(random_bytes(32)),
            1,
            base64_encode('whoever'),
        ))->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIAL');
    }

    public function test_a_tampered_assertion_is_rejected(): void
    {
        $user = User::factory()->create();
        $authenticator = new WebAuthnAuthenticator();
        $handle = $this->registerPasskey($user, $authenticator);

        $loginOptions = $this->postJson('/api/v1/auth/webauthn/login/options', ['email' => $user->email])
            ->assertOk()
            ->json('data.publicKey');

        $assertion = $authenticator->assertion(
            self::RP_ID,
            self::ORIGIN,
            $loginOptions['challenge'],
            1,
            base64_encode($handle),
        );

        // Corrupt the signature so verification must fail.
        $assertion['response']['signature'] = Base64UrlSafe::encodeUnpadded(random_bytes(70));

        $this->postJson('/api/v1/auth/webauthn/login', $assertion)
            ->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_CREDENTIAL');
    }

    public function test_passkeys_can_be_listed_and_removed(): void
    {
        $user = User::factory()->create();
        $authenticator = new WebAuthnAuthenticator();
        $this->registerPasskey($user, $authenticator);

        $token = $this->tokenFor($user);

        $id = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/webauthn/credentials')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->json('data.items.0.id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/auth/webauthn/credentials/'.$id)
            ->assertOk();

        $this->assertDatabaseCount('platform_webauthn_credentials', 0);
    }

    public function test_another_user_cannot_remove_a_passkey(): void
    {
        $owner = User::factory()->create();
        $authenticator = new WebAuthnAuthenticator();
        $this->registerPasskey($owner, $authenticator);

        $ownerToken = $this->tokenFor($owner);
        $credentialId = $this->withHeader('Authorization', 'Bearer '.$ownerToken)
            ->getJson('/api/v1/auth/webauthn/credentials')
            ->json('data.items.0.id');

        $intruder = User::factory()->create();
        $token = $this->tokenFor($intruder);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/auth/webauthn/credentials/'.$credentialId)
            ->assertStatus(404);

        $this->assertDatabaseCount('platform_webauthn_credentials', 1);
    }
}
