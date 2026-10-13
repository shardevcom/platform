<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-072: GET /api/v1/configuration over the ConfigurationRegistry.
 *
 * The behaviour under test is partial availability. A client bootstraps
 * several providers at once, so the interesting assertions are the mixed ones:
 * one unknown key must not erase the keys that did resolve, and one key the
 * caller may not read must not leak its contents either.
 *
 * Uses admin platform routes (/admin/api/v1/...) with AdminUser tokens.
 */
final class ConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_requested_providers(): void
    {
        $this->actingAsToken();

        $this->getJson('/admin/api/v1/configuration?include=core,preferences')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.core.status', 'success')
            ->assertJsonPath('data.core.data.locale', 'en')
            ->assertJsonPath('data.preferences.status', 'success');
    }

    public function test_an_unknown_provider_downgrades_the_whole_envelope_to_partial(): void
    {
        $this->actingAsToken(['configuration.admin']);

        $response = $this->getJson('/admin/api/v1/configuration?include=core,preferences,inexistente')
            ->assertStatus(207)
            ->assertJsonPath('status', 'partial')
            ->assertJsonPath('data.core.status', 'success')
            ->assertJsonPath('data.preferences.status', 'success')
            ->assertJsonPath('data.inexistente.status', 'error')
            ->assertJsonPath('data.inexistente.error.code', 'NOT_FOUND');
    }

    public function test_a_forbidden_provider_is_reported_without_leaking_its_data(): void
    {
        // Guest on web platform (no auth required for public providers)
        $response = $this->getJson('/api/v1/configuration?include=core,preferences')
            ->assertStatus(207)
            ->assertJsonPath('data.core.status', 'success')
            ->assertJsonPath('data.preferences.error.code', 'FORBIDDEN');

        $this->assertArrayNotHasKey('data', $response->json('data.preferences'));
    }

    public function test_an_empty_include_returns_only_what_the_caller_may_see(): void
    {
        // Guest on web platform
        $this->getJson('/api/v1/configuration')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.core.status', 'success');
    }

    public function test_an_authenticated_caller_gets_more_from_the_same_empty_include(): void
    {
        // Authenticated on admin platform
        $this->actingAsToken();

        $response = $this->getJson('/admin/api/v1/configuration')
            ->assertOk()
            ->assertJsonPath('data.preferences.status', 'success');
    }

    public function test_blank_entries_in_include_are_ignored(): void
    {
        $this->actingAsToken();

        $this->getJson('/admin/api/v1/configuration?include=core,%20,%20,preferences')
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    /**
     * @param list<string> $abilities
     */
    private function actingAsToken(array $abilities = []): void
    {
        $user = AdminUser::factory()->create();
        $token = $user->createToken('phpunit', $abilities)->accessToken;

        $this->withHeader('Authorization', 'Bearer '.$token);
    }
}