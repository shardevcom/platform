<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Tests\TestCase;

/**
 * T-002 acceptance: platform config loads from env, sqlite-first boot.
 */
final class PlatformConfigTest extends TestCase
{
    public function test_platform_config_has_expected_shape(): void
    {
        $this->assertSame('1.0', config('platform.version'));
        $this->assertTrue(config('platform.api.rest'));
        $this->assertTrue(config('platform.api.graphql'));
    }

    public function test_default_database_is_sqlite(): void
    {
        $this->assertSame('sqlite', config('database.default'));
    }
}
