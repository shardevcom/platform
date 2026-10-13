<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Illuminate\Support\ServiceProvider;
use Shardevcom\Core\PackageManifest;
use Tests\TestCase;

/**
 * T-001 acceptance: official packages discoverable, providers valid,
 * no namespace collision with App\.
 */
final class PackageDiscoveryTest extends TestCase
{
    public function test_discovers_official_packages(): void
    {
        $discovered = (new PackageManifest())->discover(base_path());

        $official = ['authorization', 'configuration', 'core', 'graphql', 'persistence', 'resources', 'rest'];

        foreach ($official as $dir) {
            $key = "shardev/{$dir}";
            $this->assertArrayHasKey($key, $discovered, "official package {$dir} must be discovered");
            $this->assertSame("shardev/{$dir}", $discovered[$key]['name']);
        }

        foreach ($discovered as $key => $package) {
            $this->assertNotNull($package['provider'], "package {$key} must declare extra.platform.provider");
            $this->assertTrue(class_exists($package['provider']), "provider {$package['provider']} must exist");
            $this->assertTrue(
                is_subclass_of($package['provider'], ServiceProvider::class),
                "provider {$package['provider']} must extend ServiceProvider"
            );
        }
    }

    public function test_discovers_user_packages(): void
    {
        $discovered = (new PackageManifest())->discover(base_path());

        $this->assertArrayHasKey('shardev/product', $discovered);
        $this->assertSame('Shardevcom\\Product\\ProductServiceProvider', $discovered['shardev/product']['provider']);
    }

    public function test_package_namespaces_do_not_collide_with_app(): void
    {
        $this->assertFalse(class_exists('App\\Core\\PlatformServiceProvider'));
        $this->assertTrue(class_exists('Shardevcom\\Core\\PlatformServiceProvider'));
    }

    public function test_platform_provider_is_registered(): void
    {
        $this->assertTrue($this->app->providerIsLoaded('Shardevcom\\Core\\PlatformServiceProvider'));
    }
}
