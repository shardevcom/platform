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
 * Acceptance for `platform config ...`.
 *
 * The point of these commands is persistence: whatever is set here must be
 * read back by later commands (package make, demo add, doctor).
 */
final class ConfigCommandTest extends TestCase
{
    private const PROJECT_PACKAGES = [
        '/app/packages/shardevcom/core/composer.json' => '{"name":"shardevcom/core","autoload":{"psr-4":{"Shardevcom\\\\Core\\\\":"src/"}},"extra":{"platform":{"provider":"Shardevcom\\\\Core\\\\PlatformServiceProvider"}}}',
        '/app/packages/shardevcom/core/src/PlatformServiceProvider.php' => '<?php',
        '/app/packages/shardevcom/billing/composer.json' => '{"name":"shardevcom/billing"}',
    ];

    public function test_shows_the_effective_configuration(): void
    {
        $filesystem = $this->filesystem();
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'config'], $filesystem, $recorder);
        $output = $recorder->stdout();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Config file', $output);
        $this->assertStringContainsString('/app/config/platform.php', $output);
        $this->assertStringContainsString('Packages dir', $output);
        $this->assertStringContainsString('Vendor', $output);
        $this->assertStringContainsString('Installed packages', $output);
    }

    public function test_changes_the_packages_directory_and_persists_it(): void
    {
        $filesystem = $this->filesystem();
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'config', 'packages', 'src/platform'], $filesystem, $recorder);

        $this->assertSame(0, $exit);

        $config = $filesystem->read('/app/config/platform.php');

        $this->assertStringContainsString("'dir' => 'src/platform'", $config);
        $this->assertStringContainsString("'vendor' => 'shardevcom'", $config);
        $this->assertStringContainsString('PLATFORM_PACKAGES_DIR=src/platform', $filesystem->read('/app/.env'));
    }

    public function test_moves_the_packages_that_were_already_installed(): void
    {
        $filesystem = $this->filesystem();

        $this->execute(['platform', 'config', 'packages', 'src/platform'], $filesystem);

        $this->assertFalse($filesystem->exists('/app/packages/shardevcom'));
        $this->assertTrue($filesystem->isFile('/app/src/platform/shardevcom/core/composer.json'));
        $this->assertTrue($filesystem->isFile('/app/src/platform/shardevcom/billing/composer.json'));
    }

    public function test_rewires_composer_when_the_packages_directory_moves(): void
    {
        $filesystem = $this->filesystem();

        $this->execute(['platform', 'config', 'packages', 'src/platform'], $filesystem);

        $composer = $filesystem->readJson('/app/composer.json');

        $this->assertSame(
            'src/platform/shardevcom/core/src/',
            $composer['autoload']['psr-4']['Shardevcom\\Core\\']
        );
        $this->assertContains(
            ['type' => 'path', 'url' => 'src/platform/*/*', 'options' => ['symlink' => true]],
            $composer['repositories']
        );
    }

    public function test_refuses_to_move_packages_onto_an_existing_directory(): void
    {
        $filesystem = $this->filesystem();
        $filesystem->write('/app/src/platform/.keep', '');
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'config', 'packages', 'src/platform'], $filesystem, $recorder);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('already exists', $recorder->stderr());
        $this->assertStringNotContainsString("'dir' => 'src/platform'", $filesystem->read('/app/config/platform.php'));
    }

    public function test_changes_the_vendor_directory_and_persists_it(): void
    {
        $filesystem = $this->filesystem();
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'config', 'vendor', 'acme'], $filesystem, $recorder);

        $this->assertSame(0, $exit);

        $config = $filesystem->read('/app/config/platform.php');

        $this->assertStringContainsString("'vendor' => 'acme'", $config);
        $this->assertStringContainsString("'dir' => 'packages'", $config);
        $this->assertStringContainsString('PLATFORM_VENDOR_NAMESPACE=acme', $filesystem->read('/app/.env'));
        $this->assertStringContainsString('Existing packages keep their directory', $recorder->stdout());
    }

    public function test_the_new_vendor_is_used_by_later_package_creation(): void
    {
        $filesystem = $this->filesystem();

        $this->execute(['platform', 'config', 'packages', 'src/platform'], $filesystem);
        $this->execute(['platform', 'config', 'vendor', 'acme'], $filesystem);
        $exit = $this->execute(['platform', 'package', 'make', 'shipping'], $filesystem);

        $this->assertSame(0, $exit);
        $this->assertTrue($filesystem->isFile('/app/src/platform/acme/shipping/composer.json'));
        $this->assertSame(
            'acme/shipping',
            $filesystem->readJson('/app/src/platform/acme/shipping/composer.json')['name']
        );
    }

    public function test_the_new_packages_directory_is_used_by_later_discovery(): void
    {
        $filesystem = $this->filesystem();
        $recorder = RecordingPrinter::create();

        $this->execute(['platform', 'config', 'packages', 'src/platform'], $filesystem);
        $exit = $this->execute(['platform', 'list'], $filesystem, $recorder);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('shardevcom/core', $recorder->stdout());
    }

    public function test_keeps_the_rest_of_an_existing_config_file(): void
    {
        $filesystem = $this->filesystem();

        $this->execute(['platform', 'config', 'vendor', 'acme'], $filesystem);

        $config = $filesystem->read('/app/config/platform.php');

        $this->assertStringContainsString("'version' => env('PLATFORM_VERSION', '0.1.0-dev')", $config);
        $this->assertStringContainsString("'rest' => env('API_REST_ENABLED', true)", $config);
    }

    public function test_reports_no_change_when_the_value_is_already_set(): void
    {
        $filesystem = $this->filesystem();
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'config', 'vendor', 'shardevcom'], $filesystem, $recorder);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Already configured', $recorder->stdout());
    }

    public function test_reads_an_env_backed_packages_block(): void
    {
        $filesystem = $this->filesystem();
        $recorder = RecordingPrinter::create();

        $filesystem->write('/app/config/platform.php', <<<'PHP'
        <?php

        return [
            'version' => env('PLATFORM_VERSION', '0.1.0-dev'),
            'packages' => [
                'dir' => env('PLATFORM_PACKAGES_DIR', 'src/packages'),
                'vendor' => env('PLATFORM_VENDOR_NAMESPACE', 'acme'),
            ],
        ];
        PHP);

        $exit = $this->execute(['platform', 'config'], $filesystem, $recorder);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('src/packages', $recorder->stdout());
        $this->assertStringContainsString('acme', $recorder->stdout());
    }

    public function test_rejects_an_invalid_packages_directory(): void
    {
        $filesystem = $this->filesystem();
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'config', 'packages', '../escape'], $filesystem, $recorder);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Invalid packages directory', $recorder->stderr());
    }

    public function test_rejects_an_absolute_packages_directory(): void
    {
        $filesystem = $this->filesystem();
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'config', 'packages', '/etc/packages'], $filesystem, $recorder);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('must be relative', $recorder->stderr());
    }

    public function test_rejects_an_invalid_vendor(): void
    {
        $filesystem = $this->filesystem();
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'config', 'vendor', 'Acme Inc'], $filesystem, $recorder);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Invalid vendor', $recorder->stderr());
    }

    public function test_rejects_an_unknown_sub_command(): void
    {
        $filesystem = $this->filesystem();
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'config', 'wat'], $filesystem, $recorder);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Unknown config sub-command', $recorder->stderr());
    }

    public function test_shows_the_workspace_manifest(): void
    {
        $filesystem = $this->workspaceFilesystem();
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'config'], $filesystem, $recorder);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Workspace: demo', $recorder->stdout());
        $this->assertStringContainsString('mobile', $recorder->stdout());
    }

    public function test_renames_the_workspace(): void
    {
        $filesystem = $this->workspaceFilesystem();

        $exit = $this->execute(['platform', 'config', 'name', 'monorepo'], $filesystem);

        $this->assertSame(0, $exit);
        $this->assertSame('monorepo', $filesystem->readJson('/app/platform.json')['name']);
    }

    public function test_rejects_an_invalid_workspace_name(): void
    {
        $filesystem = $this->workspaceFilesystem();
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'config', 'name', 'My App'], $filesystem, $recorder);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Invalid workspace name', $recorder->stderr());
        $this->assertSame('demo', $filesystem->readJson('/app/platform.json')['name']);
    }

    public function test_sets_the_guards_of_a_component(): void
    {
        $filesystem = $this->workspaceFilesystem();

        $exit = $this->execute(['platform', 'config', 'guard', 'web', 'employee,admin'], $filesystem);

        $this->assertSame(0, $exit);
        $this->assertSame(
            ['employee', 'admin'],
            $filesystem->readJson('/app/platform.json')['components']['web']['guards']
        );
    }

    public function test_rejects_changing_the_api_guards(): void
    {
        $filesystem = $this->workspaceFilesystem();
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'config', 'guard', 'api', 'employee'], $filesystem, $recorder);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('guard add', $recorder->stderr());
    }

    public function test_disables_and_enables_a_component(): void
    {
        $filesystem = $this->workspaceFilesystem();

        $this->execute(['platform', 'config', 'disable', 'web'], $filesystem);
        $this->assertFalse($filesystem->readJson('/app/platform.json')['components']['web']['enabled']);

        $this->execute(['platform', 'config', 'enable', 'web'], $filesystem);
        $this->assertTrue($filesystem->readJson('/app/platform.json')['components']['web']['enabled']);
    }

    public function test_workspace_config_requires_a_workspace(): void
    {
        $filesystem = $this->filesystem();
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'config', 'name', 'monorepo'], $filesystem, $recorder);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('not a workspace', $recorder->stderr());
    }

    private function workspaceFilesystem(): FakeFilesystem
    {
        $filesystem = $this->filesystem();
        $filesystem->write('/app/platform.json', '{"name":"demo","platform":"0.1.0","components":{'
            .'"api":{"type":"api","path":"api","guards":["web","admin"],"enabled":true},'
            .'"web":{"type":"web","path":"web","guards":["web"],"enabled":true},'
            .'"mobile":{"type":"mobile","path":"mobile","guards":["web"],"enabled":false}}}');

        return $filesystem;
    }

    private function filesystem(): FakeFilesystem
    {
        return new FakeFilesystem([
            '/app/composer.json' => '{"require":{"laravel/framework":"^12.0"},"autoload":{"psr-4":{"App\\\\":"app/"}}}',
            '/app/config/platform.php' => <<<'PHP'
            <?php

            return [
                'version' => env('PLATFORM_VERSION', '0.1.0-dev'),
                'api' => [
                    'rest' => env('API_REST_ENABLED', true),
                    'graphql' => env('API_GRAPHQL_ENABLED', true),
                ],
            ];
            PHP,
            '/app/.env' => "APP_NAME=Test\nDB_CONNECTION=sqlite\n",
            ...self::PROJECT_PACKAGES,
        ]);
    }

    /**
     * @param list<string> $argv
     */
    private function execute(array $argv, FakeFilesystem $filesystem, ?RecordingPrinter $recorder = null): int
    {
        $recorder ??= RecordingPrinter::create();
        $process = new FakeProcessRunner();

        $context = new Context(
            output: $recorder->printer(),
            filesystem: $filesystem,
            process: $process,
            workingDirectory: '/app',
            frameworkRoot: '/framework',
            catalog: new PackageCatalog($filesystem, '/framework'),
        );

        return (new Application($context))->run($argv);
    }
}