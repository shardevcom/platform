<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use Shardevcom\Cli\Packages\PackageCatalog;
use Tests\Unit\Cli\Support\FakeFilesystem;

/**
 * Acceptance for dynamic package discovery: the wiring of an installed project
 * is derived from what is on disk, so no package name can be hardcoded in the
 * installer.
 */
final class PackageCatalogTest extends TestCase
{
    private const PACKAGE = <<<'JSON'
    {
        "name": "shardevcom/widgets",
        "autoload": { "psr-4": { "Shardevcom\\Widgets\\": "src/" } },
        "extra": { "platform": { "provider": "Shardevcom\\Widgets\\WidgetsServiceProvider" } }
    }
    JSON;

    private const CORE = <<<'JSON'
    {
        "name": "shardevcom/core",
        "autoload": { "psr-4": { "Shardevcom\\Core\\": "src/" } },
        "extra": { "platform": { "provider": "Shardevcom\\Core\\PlatformServiceProvider" } }
    }
    JSON;

    public function test_discovers_every_package_under_the_packages_directory(): void
    {
        $catalog = $this->catalog();

        $this->assertSame(['shardevcom/core', 'shardevcom/widgets'], array_map(
            static fn ($package): string => $package->key(),
            $catalog->packages()
        ));
    }

    public function test_reads_namespace_and_provider_from_the_package_manifest(): void
    {
        $widgets = $this->catalog()->packages()[1];

        $this->assertSame(['Shardevcom\\Widgets\\' => 'src'], $widgets->psr4);
        $this->assertSame('Shardevcom\\Widgets\\WidgetsServiceProvider', $widgets->provider);
        $this->assertSame('shardevcom/widgets', $widgets->name);
    }

    public function test_builds_the_psr4_map_of_a_generated_project(): void
    {
        $this->assertSame([
            'Shardevcom\\Core\\' => 'packages/shardevcom/core/src/',
            'Shardevcom\\Widgets\\' => 'packages/shardevcom/widgets/src/',
        ], $this->catalog()->psr4Map());
    }

    public function test_builds_the_requirements_of_a_generated_project(): void
    {
        $this->assertSame([
            'shardevcom/core' => '*',
            'shardevcom/widgets' => '*',
        ], $this->catalog()->requirements());
    }

    public function test_registers_only_the_bootstrapper_and_lets_it_register_the_rest(): void
    {
        $catalog = $this->catalog();

        $this->assertSame('Shardevcom\\Core\\PlatformServiceProvider', $catalog->platformBootstrapper());
        $this->assertSame(['Shardevcom\\Core\\PlatformServiceProvider'], $catalog->providersForBootstrap());
        $this->assertSame([
            'Shardevcom\\Core\\PlatformServiceProvider',
            'Shardevcom\\Widgets\\WidgetsServiceProvider',
        ], $catalog->providers());
    }

    public function test_without_a_bootstrapper_every_provider_must_be_registered(): void
    {
        $catalog = $this->catalog([
            '/framework/packages/shardevcom/widgets/composer.json' => self::PACKAGE,
            '/framework/packages/shardevcom/widgets/src/WidgetsServiceProvider.php' => '<?php',
        ]);

        $this->assertNull($catalog->platformBootstrapper());
        $this->assertSame(['Shardevcom\\Widgets\\WidgetsServiceProvider'], $catalog->providersForBootstrap());
    }

    public function test_a_package_without_provider_is_still_discovered_but_flagged(): void
    {
        $catalog = $this->catalog([
            '/framework/packages/shardevcom/tools/composer.json' => '{"name":"shardevcom/tools"}',
        ]);

        $this->assertCount(1, $catalog->packages());
        $this->assertFalse($catalog->packages()[0]->hasProvider());
        $this->assertSame([], $catalog->providers());
    }

    public function test_repository_glob_covers_both_layouts(): void
    {
        $this->assertSame('packages/*/*', $this->catalog()->repositoryGlob());
    }

    /**
     * @param array<string, string>|null $files
     */
    private function catalog(?array $files = null): PackageCatalog
    {
        $filesystem = new FakeFilesystem($files ?? [
            '/framework/packages/shardevcom/core/composer.json' => self::CORE,
            '/framework/packages/shardevcom/core/src/PlatformServiceProvider.php' => '<?php',
            '/framework/packages/shardevcom/widgets/composer.json' => self::PACKAGE,
            '/framework/packages/shardevcom/widgets/src/WidgetsServiceProvider.php' => '<?php',
        ]);

        return new PackageCatalog($filesystem, '/framework');
    }
}