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
 * Acceptance for `platform new` as a whole.
 *
 * The whole flow runs against a fake filesystem and a fake shell, so what is
 * asserted is the wiring the installer produces (composer.json, providers,
 * .env) and the commands it would run.
 */
final class NewCommandTest extends TestCase
{
    private const FRAMEWORK_PACKAGES = [
        '/framework/packages/shardevcom/core/composer.json' => <<<'JSON'
        {
            "name": "shardevcom/core",
            "autoload": { "psr-4": { "Shardevcom\\Core\\": "src/" } },
            "extra": { "platform": { "provider": "Shardevcom\\Core\\PlatformServiceProvider" } }
        }
        JSON,
        '/framework/packages/shardevcom/core/src/PlatformServiceProvider.php' => '<?php',
        '/framework/packages/shardevcom/widgets/composer.json' => <<<'JSON'
        {
            "name": "shardevcom/widgets",
            "autoload": { "psr-4": { "Shardevcom\\Widgets\\": "src/" } },
            "extra": { "platform": { "provider": "Shardevcom\\Widgets\\WidgetsServiceProvider" } }
        }
        JSON,
        '/framework/packages/shardevcom/widgets/src/WidgetsServiceProvider.php' => '<?php',
        '/framework/demo/web/package.json' => '{"name":"shardev-web"}',
        '/framework/demo/web/apps/admin/src/main.tsx' => 'console.log(1)',
    ];

    private const WORKSPACE = '/tmp/workspace/new-app';

    private const PROJECT = self::WORKSPACE.'/api';

    public function test_wires_every_discovered_package_into_the_generated_project(): void
    {
        $filesystem = $this->filesystem();
        $exit = $this->execute(['platform', 'new', 'new-app'], $filesystem);

        $this->assertSame(0, $exit);

        $composer = $filesystem->readJson(self::PROJECT.'/composer.json');

        $this->assertSame([
            'App\\' => 'app/',
            'Shardevcom\\Core\\' => 'packages/shardevcom/core/src/',
            'Shardevcom\\Widgets\\' => 'packages/shardevcom/widgets/src/',
        ], $composer['autoload']['psr-4']);
        $this->assertSame('*', $composer['require']['shardevcom/core']);
        $this->assertSame('*', $composer['require']['shardevcom/widgets']);
        $this->assertContains(
            ['type' => 'path', 'url' => 'packages/*/*', 'options' => ['symlink' => true]],
            $composer['repositories']
        );
        $this->assertSame('dev', $composer['minimum-stability']);
    }

    public function test_keeps_existing_psr4_mappings_and_packagist_repository(): void
    {
        $this->execute(['platform', 'new', 'new-app']);

        $composer = $this->filesystem()->readJson(self::PROJECT.'/composer.json');

        $this->assertSame('app/', $composer['autoload']['psr-4']['App\\']);
        $this->assertContains(
            ['type' => 'composer', 'url' => 'https://repo.packagist.org'],
            $composer['repositories']
        );
    }

    public function test_copies_the_packages_being_installed(): void
    {
        $this->execute(['platform', 'new', 'new-app']);

        $this->assertTrue(
            $this->filesystem()->isFile(self::PROJECT.'/packages/shardevcom/core/composer.json')
        );
        $this->assertTrue(
            $this->filesystem()->isFile(self::PROJECT.'/packages/shardevcom/widgets/src/WidgetsServiceProvider.php')
        );
    }

    public function test_registers_only_the_platform_bootstrapper(): void
    {
        $this->execute(['platform', 'new', 'new-app']);

        $providers = $this->filesystem()->read(self::PROJECT.'/bootstrap/providers.php');

        $this->assertStringContainsString('Shardevcom\Core\PlatformServiceProvider::class', $providers);
        $this->assertStringNotContainsString('WidgetsServiceProvider', $providers);
        $this->assertStringContainsString('AppServiceProvider', $providers);
    }

    public function test_prepares_sqlite_env_and_database_file(): void
    {
        $this->execute(['platform', 'new', 'new-app']);

        $env = $this->filesystem()->read(self::PROJECT.'/.env');

        $this->assertStringContainsString('DB_CONNECTION=sqlite', $env);
        $this->assertStringContainsString('DB_DATABASE=database/database.sqlite', $env);
        $this->assertStringContainsString('PLATFORM_VERSION=', $env);
        $this->assertTrue($this->filesystem()->isFile(self::PROJECT.'/database/database.sqlite'));
    }

    public function test_writes_the_packages_configuration_a_later_command_reads(): void
    {
        $filesystem = $this->filesystem();
        $recorder = RecordingPrinter::create();

        $this->execute(['platform', 'new', 'new-app'], $filesystem, null, null, $recorder);

        $config = $filesystem->read(self::PROJECT.'/config/platform.php');

        $this->assertStringContainsString("'packages' => [", $config);
        $this->assertStringContainsString("'dir' => 'packages'", $config);
        $this->assertStringContainsString("'vendor' => 'shardevcom'", $config);
        $this->assertStringContainsString('PLATFORM_PACKAGES_DIR=packages', $filesystem->read(self::PROJECT.'/.env'));
        $this->assertStringContainsString('PLATFORM_VENDOR_NAMESPACE=shardevcom', $filesystem->read(self::PROJECT.'/.env'));

        $context = new Context(
            output: $recorder->printer(),
            filesystem: $filesystem,
            process: new FakeProcessRunner(),
            workingDirectory: self::PROJECT,
            frameworkRoot: '/framework',
            catalog: new PackageCatalog($filesystem, '/framework'),
        );

        $this->assertSame('packages', $context->config()->packagesDir);
        $this->assertSame('shardevcom', $context->config()->vendorNamespace);
    }

    public function test_writes_platform_config_when_absent(): void
    {
        $this->execute(['platform', 'new', 'new-app']);

        $config = $this->filesystem()->read(self::PROJECT.'/config/platform.php');

        $this->assertStringContainsString("'rest'", $config);
        $this->assertStringContainsString("'graphql'", $config);
    }

    public function test_runs_the_install_pipeline_inside_the_project(): void
    {
        $this->execute(['platform', 'new', 'new-app']);

        $process = $this->process();

        $this->assertTrue($process->ran('composer create-project laravel/laravel'));
        $this->assertTrue($process->ran('composer update'));
        $this->assertTrue($process->ran('key:generate'));
        $this->assertTrue($process->ran('artisan migrate'));
        $this->assertTrue($process->ran('composer dump-autoload'));
        $this->assertTrue($process->ranIn('artisan migrate', self::PROJECT));
    }

    public function test_passes_no_interaction_to_composer_when_asked(): void
    {
        $this->execute(['platform', 'new', 'new-app', '--no-interaction']);

        $this->assertStringContainsString('--no-interaction', $this->process()->commands()[0]);
    }

    public function test_translates_a_major_laravel_number_into_a_constraint(): void
    {
        $this->execute(['platform', 'new', 'new-app', '--laravel=13']);

        $this->assertStringContainsString("'^13.0'", $this->process()->commands()[0]);
    }

    public function test_fails_when_a_verification_command_fails(): void
    {
        $process = new FakeProcessRunner([
            ['needle' => 'route:list --path=graphql', 'exitCode' => 1, 'output' => 'no route'],
        ]);

        $exit = $this->execute(['platform', 'new', 'new-app'], null, $process);

        $this->assertSame(1, $exit);
    }

    public function test_rejects_an_unsupported_laravel_version_before_touching_anything(): void
    {
        $process = new FakeProcessRunner();

        $exit = $this->execute(['platform', 'new', 'new-app', '--laravel=9'], null, $process);

        $this->assertSame(1, $exit);
        $this->assertSame([], $process->commands());
    }

    public function test_rejects_an_invalid_project_name(): void
    {
        $exit = $this->execute(['platform', 'new', 'My App']);

        $this->assertSame(1, $exit);
    }

    public function test_refuses_to_overwrite_an_existing_directory(): void
    {
        $filesystem = $this->filesystem();
        $filesystem->createDirectory(self::WORKSPACE);

        $exit = $this->execute(['platform', 'new', 'new-app'], $filesystem);

        $this->assertSame(1, $exit);
    }

    public function test_writes_the_workspace_manifest(): void
    {
        $this->execute(['platform', 'new', 'new-app']);

        $manifest = $this->filesystem()->readJson(self::WORKSPACE.'/platform.json');

        $this->assertSame('new-app', $manifest['name']);
        $this->assertSame('api', $manifest['components']['api']['type']);
        $this->assertSame('api', $manifest['components']['api']['path']);
    }

    public function test_drops_an_executable_control_shell_at_the_workspace_root(): void
    {
        $filesystem = $this->filesystem();

        $exit = $this->execute(['platform', 'new', 'new-app'], $filesystem);

        $this->assertSame(0, $exit);
        $this->assertTrue($filesystem->isFile(self::WORKSPACE.'/platform.sh'));
        $this->assertTrue($filesystem->isExecutable(self::WORKSPACE.'/platform.sh'));
        $this->assertStringContainsString(
            'control shell',
            $filesystem->read(self::WORKSPACE.'/platform.sh')
        );
    }

    public function test_clients_mirror_the_api_platforms_by_default(): void
    {
        $filesystem = $this->filesystem();

        $exit = $this->execute(['platform', 'new', 'new-app', '--with=api,web'], $filesystem);

        $this->assertSame(0, $exit);
        $manifest = $filesystem->readJson(self::WORKSPACE.'/platform.json');

        $expected = [
            'web' => ['public' => true],
            'admin' => ['public' => false],
            'employee' => ['public' => false],
        ];
        $this->assertSame($expected, $manifest['components']['api']['guards']);
        $this->assertSame($expected, $manifest['components']['web']['guards']);
    }

    public function test_pins_the_client_guards_when_asked(): void
    {
        $filesystem = $this->filesystem();

        $exit = $this->execute(['platform', 'new', 'new-app', '--with=api,web', '--guards=employee'], $filesystem);

        $this->assertSame(0, $exit);
        $manifest = $filesystem->readJson(self::WORKSPACE.'/platform.json');

        $this->assertSame(['employee' => ['public' => false]], $manifest['components']['web']['guards']);
        $this->assertSame(
            [
                'web' => ['public' => true],
                'admin' => ['public' => false],
                'employee' => ['public' => false],
            ],
            $manifest['components']['api']['guards']
        );
    }

    public function test_pins_the_client_guards_without_an_api(): void
    {
        $filesystem = $this->filesystem();

        $exit = $this->execute(['platform', 'new', 'new-app', '--with=web', '--guards=employee'], $filesystem);

        $this->assertSame(0, $exit);
        $manifest = $filesystem->readJson(self::WORKSPACE.'/platform.json');

        $this->assertSame(['employee' => ['public' => false]], $manifest['components']['web']['guards']);
        $this->assertArrayNotHasKey('api', $manifest['components']);
    }

    public function test_accepts_visibility_suffixes(): void
    {
        $filesystem = $this->filesystem();

        $exit = $this->execute(
            ['platform', 'new', 'new-app', '--with=web', '--guards=web:private,admin:public'],
            $filesystem
        );

        $this->assertSame(0, $exit);
        $manifest = $filesystem->readJson(self::WORKSPACE.'/platform.json');

        $this->assertSame(
            ['web' => ['public' => false], 'admin' => ['public' => true]],
            $manifest['components']['web']['guards']
        );
    }

    public function test_rejects_an_invalid_visibility(): void
    {
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(
            ['platform', 'new', 'new-app', '--with=web', '--guards=web:everyone'],
            null,
            null,
            $recorder
        );

        $this->assertSame(1, $exit);
        $this->assertStringContainsString("Invalid visibility 'everyone'", $recorder->stderr());
    }

    public function test_rejects_an_invalid_guard(): void
    {
        $exit = $this->execute(['platform', 'new', 'new-app', '--with=web', '--guards=Employee!']);

        $this->assertSame(1, $exit);
    }

    public function test_scaffolds_the_api_as_a_component_directory(): void
    {
        $this->execute(['platform', 'new', 'new-app']);

        $this->assertTrue($this->filesystem()->isDirectory(self::PROJECT));
        $this->assertTrue($this->filesystem()->isFile(self::PROJECT.'/composer.json'));
    }

    public function test_creates_only_the_components_requested(): void
    {
        $filesystem = $this->filesystem();

        $exit = $this->execute(['platform', 'new', 'new-app', '--with=api,web'], $filesystem);

        $this->assertSame(0, $exit);
        $this->assertTrue($filesystem->isFile(self::WORKSPACE.'/web/package.json'));

        $manifest = $filesystem->readJson(self::WORKSPACE.'/platform.json');
        $this->assertArrayHasKey('web', $manifest['components']);
        $this->assertArrayNotHasKey('mobile', $manifest['components']);
    }

    public function test_rejects_an_unknown_component(): void
    {
        $exit = $this->execute(['platform', 'new', 'new-app', '--with=api,billing']);

        $this->assertSame(1, $exit);
    }

    private function filesystem(): FakeFilesystem
    {
        return $this->filesystem ??= new FakeFilesystem(self::FRAMEWORK_PACKAGES);
    }

    private function process(): FakeProcessRunner
    {
        return $this->process ??= new FakeProcessRunner([
            ['needle' => 'route:list --path=api/v1', 'output' => 'api/v1/products'],
            ['needle' => 'route:list --path=graphql', 'output' => 'graphql'],
            ['needle' => 'config:show platform', 'output' => 'platform'],
            ['needle' => 'artisan --version', 'output' => 'Laravel Framework 12.0'],
        ]);
    }

    /**
     * @param list<string> $argv
     */
    private function execute(
        array $argv,
        ?FakeFilesystem $filesystem = null,
        ?FakeProcessRunner $process = null,
        ?RecordingPrinter $recorder = null,
    ): int {
        $this->lastRecorder = $recorder;
        $this->filesystem = $filesystem ?? $this->filesystem();
        $this->process = $process ?? $this->process();

        $this->simulateCreateProject($this->filesystem, $this->process);

        $context = new Context(
            output: ($this->lastRecorder ?? RecordingPrinter::create())->printer(),
            filesystem: $this->filesystem,
            process: $this->process,
            workingDirectory: '/tmp/workspace',
            frameworkRoot: '/framework',
            catalog: new PackageCatalog($this->filesystem, '/framework'),
        );

        return (new Application($context))->run($argv);
    }

    /**
     * `composer create-project` is the only step that materialises the Laravel
     * skeleton, so the fake shell has to reproduce that side effect.
     */
    private function simulateCreateProject(FakeFilesystem $filesystem, FakeProcessRunner $process): void
    {
        $process->after('create-project', function () use ($filesystem): void {
            $project = self::PROJECT;

            $filesystem->createDirectory($project);
            $filesystem->write($project.'/composer.json', <<<'JSON'
            {
                "name": "laravel/laravel",
                "require": { "php": "^8.2", "laravel/framework": "^12.0" },
                "autoload": { "psr-4": { "App\\": "app/" } },
                "repositories": [
                    { "type": "composer", "url": "https://repo.packagist.org" }
                ],
                "minimum-stability": "stable"
            }
            JSON);
            $filesystem->write($project.'/bootstrap/providers.php', <<<'PHP'
            <?php

            return [
                App\Providers\AppServiceProvider::class,
            ];
            PHP);
            $filesystem->write($project.'/.env.example', "APP_NAME=Laravel\nDB_CONNECTION=mysql\n");
            $filesystem->write($project.'/config/platforms.php', <<<'PHP'
            <?php

            return [
                'platforms' => [
                    'web' => ['key' => 'web'],
                    'admin' => ['key' => 'admin'],
                    'employee' => ['key' => 'employee'],
                ],
            ];
            PHP);
        });
    }

    private ?FakeFilesystem $filesystem = null;

    private ?FakeProcessRunner $process = null;

    private ?RecordingPrinter $lastRecorder = null;
}