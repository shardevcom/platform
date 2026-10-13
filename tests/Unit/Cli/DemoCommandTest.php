<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use Shardevcom\Cli\Application;
use Shardevcom\Cli\Context;
use Shardevcom\Cli\Packages\PackageCatalog;
use Tests\Unit\Cli\Support\FakeFilesystem;
use Tests\Unit\Cli\Support\FakeProcessRunner;
use Tests\Unit\Cli\Support\RecordingPrinter;

/**
 * Acceptance for `platform demo ...`.
 *
 * The demo has to land inside the project's configured packages directory and
 * vendor, and the installed copy must be a working package: composer wired,
 * migrations ran, and its platform requirements pointing at packages this
 * project actually has.
 */
final class DemoCommandTest extends TestCase
{
    private const DEMO_PACKAGE = '/framework/demo/packages/shardev/product';

    public function test_lists_the_packages_the_demo_ships(): void
    {
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'demo', 'list'], $this->filesystem(), $recorder);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('product', $recorder->stdout());
    }

    public function test_installs_the_demo_into_the_configured_packages_directory(): void
    {
        $filesystem = $this->filesystem();
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'demo', 'add'], $filesystem, $recorder);

        $this->assertSame(0, $exit, $recorder->output());
        $this->assertTrue(
            $filesystem->isFile('/app/src/platform/acme/product/src/ProductServiceProvider.php'),
            'demo files must be copied into the project'
        );
        $this->assertTrue($filesystem->isFile(
            '/app/src/platform/acme/product/database/migrations/2026_10_01_000000_create_products_tables.php'
        ));
        $this->assertStringContainsString('acme/product', $recorder->stdout());
    }

    public function test_renames_the_demo_to_the_configured_vendor(): void
    {
        $filesystem = $this->filesystem();

        $this->execute(['platform', 'demo', 'add', 'product'], $filesystem);

        $manifest = $filesystem->readJson('/app/src/platform/acme/product/composer.json');

        $this->assertSame('acme/product', $manifest['name']);
        $this->assertSame(['Shardevcom\\Product\\' => 'src/'], $manifest['autoload']['psr-4']);
    }

    public function test_maps_demo_requirements_onto_the_installed_packages(): void
    {
        $filesystem = $this->filesystem();

        $this->execute(['platform', 'demo', 'add', 'product'], $filesystem);

        $manifest = $filesystem->readJson('/app/src/platform/acme/product/composer.json');

        // The demo requires shardev/core; this project installed shardevcom/core.
        $this->assertSame('^8.3', $manifest['require']['php']);
        $this->assertArrayHasKey('shardevcom/core', $manifest['require']);
        $this->assertArrayNotHasKey('shardev/core', $manifest['require']);
        $this->assertArrayHasKey('shardevcom/persistence', $manifest['require']);
    }

    public function test_wires_the_installed_demo_into_composer(): void
    {
        $filesystem = $this->filesystem();

        $this->execute(['platform', 'demo', 'add', 'product'], $filesystem);

        $composer = $filesystem->readJson('/app/composer.json');

        $this->assertSame(
            'src/platform/acme/product/src/',
            $composer['autoload']['psr-4']['Shardevcom\\Product\\']
        );
        $this->assertArrayHasKey('acme/product', $composer['require']);
        $this->assertContains(
            ['type' => 'path', 'url' => 'src/platform/*/*', 'options' => ['symlink' => true]],
            $composer['repositories']
        );
    }

    public function test_runs_composer_and_migrations_in_the_project(): void
    {
        $process = new FakeProcessRunner();
        $filesystem = $this->filesystem();

        $this->execute(['platform', 'demo', 'add', 'product'], $filesystem, null, $process);

        $this->assertTrue($process->ranIn('composer update --no-interaction', '/app'));
        $this->assertTrue($process->ranIn('composer dump-autoload', '/app'));
        $this->assertTrue($process->ranIn('artisan migrate --force', '/app'));
    }

    public function test_refuses_to_install_the_same_demo_twice(): void
    {
        $filesystem = $this->filesystem();

        $this->execute(['platform', 'demo', 'add', 'product'], $filesystem);
        $recorder = RecordingPrinter::create();
        $exit = $this->execute(['platform', 'demo', 'add', 'product'], $filesystem, $recorder);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('already installed', $recorder->stderr());
    }

    public function test_reports_an_unknown_demo_package(): void
    {
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'demo', 'add', 'billing'], $this->filesystem(), $recorder);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('is not available', $recorder->stderr());
        $this->assertStringContainsString('product', $recorder->stderr());
    }

    public function test_follows_the_packages_directory_changed_earlier(): void
    {
        $filesystem = $this->filesystem();

        $this->execute(['platform', 'config', 'packages', 'modules'], $filesystem);
        $exit = $this->execute(['platform', 'demo', 'add', 'product'], $filesystem);

        $this->assertSame(0, $exit);
        $this->assertTrue($filesystem->isFile('/app/modules/acme/product/composer.json'));
    }

    public function test_rejects_an_unknown_demo_sub_command(): void
    {
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'demo', 'wat'], $this->filesystem(), $recorder);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Unknown demo sub-command', $recorder->stderr());
    }

    private function filesystem(): FakeFilesystem
    {
        return new FakeFilesystem([
            '/app/composer.json' => '{"require":{"laravel/framework":"^12.0"},"autoload":{"psr-4":{"App\\\\":"app/"}}}',
            '/app/config/platform.php' => <<<'PHP'
            <?php

            return [
                'packages' => [
                    'dir' => 'src/platform',
                    'vendor' => 'acme',
                ],
            ];
            PHP,
            '/app/.env' => "APP_NAME=Test\n",
            '/app/src/platform/shardevcom/core/composer.json' => '{"name":"shardevcom/core","autoload":{"psr-4":{"Shardevcom\\\\Core\\\\":"src/"}}}',
            '/app/src/platform/shardevcom/core/src/PlatformServiceProvider.php' => '<?php',
            '/app/src/platform/shardevcom/persistence/composer.json' => '{"name":"shardevcom/persistence"}',
            '/app/src/platform/shardevcom/resources/composer.json' => '{"name":"shardevcom/resources"}',
            self::DEMO_PACKAGE.'/composer.json' => <<<'JSON'
            {
                "name": "shardev/product",
                "autoload": { "psr-4": { "Shardevcom\\Product\\": "src/" } },
                "extra": { "platform": { "provider": "Shardevcom\\Product\\ProductServiceProvider" } },
                "require": { "php": "^8.3", "shardev/core": "@dev", "shardev/persistence": "@dev", "shardev/resources": "@dev" }
            }
            JSON,
            self::DEMO_PACKAGE.'/src/ProductServiceProvider.php' => '<?php',
            self::DEMO_PACKAGE.'/src/Models/Product.php' => '<?php',
            self::DEMO_PACKAGE.'/routes/api.php' => '<?php',
            self::DEMO_PACKAGE.'/database/migrations/2026_10_01_000000_create_products_tables.php' => '<?php',
        ]);
    }

    /**
     * @param list<string> $argv
     */
    private function execute(
        array $argv,
        FakeFilesystem $filesystem,
        ?RecordingPrinter $recorder = null,
        ?FakeProcessRunner $process = null,
    ): int {
        $recorder ??= RecordingPrinter::create();

        $context = new Context(
            output: $recorder->printer(),
            filesystem: $filesystem,
            process: $process ?? new FakeProcessRunner(),
            workingDirectory: '/app',
            frameworkRoot: '/framework',
            catalog: new PackageCatalog($filesystem, '/framework'),
        );

        return (new Application($context))->run($argv);
    }
}