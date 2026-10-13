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
 * Acceptance for `platform package make`: the generated package must be
 * discoverable afterwards, which is what makes the installer hardcode-free.
 */
final class PackageMakeCommandTest extends TestCase
{
    public function test_creates_a_package_that_discovery_can_then_read(): void
    {
        $filesystem = $this->filesystem();
        $exit = $this->execute(['platform', 'package', 'make', 'billing'], $filesystem);

        $this->assertSame(0, $exit);

        $manifest = $filesystem->readJson('/framework/packages/shardevcom/billing/composer.json');

        $this->assertSame('shardevcom/billing', $manifest['name']);
        $this->assertSame(
            ['Shardevcom\\Billing\\' => 'src/'],
            $manifest['autoload']['psr-4']
        );
        $this->assertSame(
            'Shardevcom\\Billing\\BillingServiceProvider',
            $manifest['extra']['platform']['provider']
        );

        $catalog = new PackageCatalog($filesystem, '/framework');

        $this->assertSame('shardevcom/billing', $catalog->packages()[0]->key());
        $this->assertSame(
            ['Shardevcom\\Billing\\' => 'packages/shardevcom/billing/src/'],
            array_filter($catalog->psr4Map(), static fn (string $key): bool => str_contains($key, 'Billing'), ARRAY_FILTER_USE_KEY)
        );
    }

    public function test_creates_the_provider_and_the_expected_structure(): void
    {
        $filesystem = $this->filesystem();
        $this->execute(['platform', 'package', 'make', 'billing'], $filesystem);

        $root = '/framework/packages/shardevcom/billing';

        foreach ([
            'composer.json',
            'src/BillingServiceProvider.php',
            'config/billing.php',
            'routes/api.php',
            'database/migrations/.gitkeep',
            'tests/.gitkeep',
        ] as $file) {
            $this->assertTrue($filesystem->isFile($root.'/'.$file), "missing {$file}");
        }

        $provider = $filesystem->read($root.'/src/BillingServiceProvider.php');

        $this->assertStringContainsString('namespace Shardevcom\\Billing;', $provider);
        $this->assertStringContainsString('class BillingServiceProvider', $provider);
    }

    public function test_dry_run_creates_nothing(): void
    {
        $filesystem = $this->filesystem();
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'package', 'make', 'billing', '--dry-run'], $filesystem, $recorder);

        $this->assertSame(0, $exit);
        $this->assertFalse($filesystem->exists('/framework/packages/shardevcom/billing'));
        $this->assertStringContainsString('composer.json', $recorder->stdout());
    }

    public function test_follows_the_projects_configured_packages_dir_and_vendor(): void
    {
        $filesystem = new FakeFilesystem([
            '/app/composer.json' => '{"require":{"laravel/framework":"^12.0"}}',
            '/app/config/platform.php' => <<<'PHP'
            <?php

            return [
                'version' => '0.1.0-dev',
                'packages' => [
                    'dir' => 'src/platform',
                    'vendor' => 'acme',
                ],
            ];
            PHP,
        ]);

        $exit = $this->execute(['platform', 'package', 'make', 'billing'], $filesystem);

        $this->assertSame(0, $exit);

        $manifest = $filesystem->readJson('/app/src/platform/acme/billing/composer.json');

        $this->assertSame('acme/billing', $manifest['name']);
        $this->assertSame(['Acme\\Billing\\' => 'src/'], $manifest['autoload']['psr-4']);
        $this->assertStringContainsString(
            'namespace Acme\\Billing;',
            $filesystem->read('/app/src/platform/acme/billing/src/BillingServiceProvider.php')
        );
    }

    public function test_uses_the_requested_vendor_directory(): void
    {
        $filesystem = $this->filesystem();

        $this->execute(['platform', 'package', 'make', 'billing', '--vendor=acme'], $filesystem);

        $this->assertTrue($filesystem->isFile('/framework/packages/acme/billing/composer.json'));
        $this->assertSame(
            ['Acme\\Billing\\' => 'src/'],
            $filesystem->readJson('/framework/packages/acme/billing/composer.json')['autoload']['psr-4']
        );
    }

    public function test_refuses_to_overwrite_an_existing_package(): void
    {
        $filesystem = $this->filesystem();
        $this->execute(['platform', 'package', 'make', 'billing'], $filesystem);
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'package', 'make', 'billing'], $filesystem, $recorder);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('already exists', $recorder->stderr());
    }

    public function test_rejects_an_invalid_name_before_writing(): void
    {
        $filesystem = $this->filesystem();
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'package', 'make', 'Core/Billing'], $filesystem, $recorder);

        $this->assertSame(1, $exit);
        $this->assertSame(['/framework/packages/shardevcom/core/composer.json'], $filesystem->writtenFiles());
    }

    public function test_wires_the_new_package_into_an_installed_project(): void
    {
        $filesystem = new FakeFilesystem([
            '/app/composer.json' => '{"require":{"laravel/framework":"^12.0"},"autoload":{"psr-4":{"App\\\\":"app/"}}}',
            '/app/packages/shardevcom/core/composer.json' => '{"name":"shardevcom/core","autoload":{"psr-4":{"Shardevcom\\\\Core\\\\":"src/"}},"extra":{"platform":{"provider":"Shardevcom\\\\Core\\\\PlatformServiceProvider"}}}',
            '/app/config/platform.php' => "<?php\n\nreturn [\n    'packages' => [\n        'dir' => 'packages',\n        'vendor' => 'shardevcom',\n    ],\n];\n",
        ]);

        $exit = $this->execute(['platform', 'package', 'make', 'billing'], $filesystem);

        $this->assertSame(0, $exit);

        $composer = $filesystem->readJson('/app/composer.json');

        $this->assertSame(
            'packages/shardevcom/billing/src/',
            $composer['autoload']['psr-4']['Shardevcom\\Billing\\'],
            'the namespace must be mapped or the autoloader cannot see the package'
        );
    }

    public function test_leaves_composer_alone_outside_an_installed_project(): void
    {
        $filesystem = $this->filesystem();
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'package', 'make', 'billing'], $filesystem, $recorder);

        $this->assertSame(0, $exit);
        $this->assertFalse($filesystem->exists('/framework/composer.json'));
        $this->assertStringContainsString('composer dump-autoload', $recorder->stdout());
    }

    private function filesystem(): FakeFilesystem
    {
        return new FakeFilesystem([
            '/framework/packages/shardevcom/core/composer.json' => '{"name":"shardevcom/core"}',
        ]);
    }

    /**
     * @param list<string> $argv
     */
    private function execute(array $argv, FakeFilesystem $filesystem, ?RecordingPrinter $recorder = null): int
    {
        $recorder ??= RecordingPrinter::create();

        $context = new Context(
            output: $recorder->printer(),
            filesystem: $filesystem,
            process: new FakeProcessRunner(),
            workingDirectory: $filesystem->isFile('/app/composer.json') ? '/app' : '/framework',
            frameworkRoot: '/framework',
            catalog: new PackageCatalog($filesystem, '/framework'),
        );

        return (new Application($context))->run($argv);
    }
}