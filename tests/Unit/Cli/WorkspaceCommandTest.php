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
 * Acceptance for the workspace control commands (status, install, build, dev,
 * add, remove) driven by platform.json rather than hardcoded component lists.
 */
final class WorkspaceCommandTest extends TestCase
{
    private const ROOT = '/ws';

    public function test_status_lists_the_components_and_their_state(): void
    {
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'status'], $this->workspaceFiles(), null, $recorder);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Workspace: demo', $recorder->stdout());
        $this->assertStringContainsString('api', $recorder->stdout());
        $this->assertStringContainsString('web', $recorder->stdout());
    }

    public function test_install_runs_each_component_driver(): void
    {
        $process = new FakeProcessRunner();

        $exit = $this->execute(['platform', 'install'], $this->workspaceFiles(), $process);

        $this->assertSame(0, $exit);
        $this->assertTrue($process->ranIn('composer update --no-interaction -n', self::ROOT.'/api'));
        $this->assertTrue($process->ranIn('npm install', self::ROOT.'/web'));
    }

    public function test_install_can_target_a_single_component(): void
    {
        $process = new FakeProcessRunner();

        $this->execute(['platform', 'install', 'web'], $this->workspaceFiles(), $process);

        $this->assertTrue($process->ranIn('npm install', self::ROOT.'/web'));
        $this->assertFalse($process->ranIn('composer update --no-interaction -n', self::ROOT.'/api'));
    }

    public function test_build_runs_the_component_driver(): void
    {
        $process = new FakeProcessRunner();

        $this->execute(['platform', 'build', 'api'], $this->workspaceFiles(), $process);

        $this->assertTrue($process->ranIn('composer dump-autoload -o', self::ROOT.'/api'));
    }

    public function test_dev_runs_the_component_toolchain(): void
    {
        $process = new FakeProcessRunner();

        $this->execute(['platform', 'dev', 'web'], $this->workspaceFiles(), $process);

        $this->assertTrue($process->ranIn('npm run dev:admin', self::ROOT.'/web'));
    }

    public function test_add_scaffolds_a_component_and_records_it(): void
    {
        $filesystem = $this->workspaceFiles();

        $exit = $this->execute(['platform', 'add', 'desktop'], $filesystem);

        $this->assertSame(0, $exit);
        $this->assertTrue($filesystem->isFile(self::ROOT.'/desktop/build.gradle.kts'));

        $manifest = $filesystem->readJson(self::ROOT.'/platform.json');
        $this->assertArrayHasKey('desktop', $manifest['components']);
    }

    public function test_add_refuses_a_component_already_in_the_workspace(): void
    {
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'add', 'web'], $this->workspaceFiles(), null, $recorder);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('already part', $recorder->stderr());
    }

    public function test_remove_drops_the_component_from_the_manifest(): void
    {
        $filesystem = $this->workspaceFiles();

        $exit = $this->execute(['platform', 'remove', 'web'], $filesystem);

        $this->assertSame(0, $exit);
        $this->assertArrayNotHasKey('web', $filesystem->readJson(self::ROOT.'/platform.json')['components']);
    }

    public function test_commands_require_a_workspace(): void
    {
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'status'], new FakeFilesystem(), null, $recorder);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('not a workspace', $recorder->stderr());
    }

    private function workspaceFiles(): FakeFilesystem
    {
        return new FakeFilesystem([
            self::ROOT.'/platform.json' => json_encode([
                'name' => 'demo',
                'platform' => '0.1.0',
                'components' => [
                    'api' => ['type' => 'api', 'path' => 'api', 'guard' => null, 'enabled' => true],
                    'web' => ['type' => 'web', 'path' => 'web', 'guard' => 'admin', 'enabled' => true],
                ],
            ]),
            self::ROOT.'/api/composer.json' => '{"require":{"laravel/framework":"^12.0"}}',
            self::ROOT.'/api/vendor/autoload.php' => '<?php',
            self::ROOT.'/web/package.json' => '{"name":"shardev-web"}',
            self::ROOT.'/web/node_modules/pkg/index.js' => '//',
            '/framework/demo/desktop/settings.gradle.kts' => 'rootProject.name = "shardev-desktop"',
            '/framework/demo/desktop/build.gradle.kts' => 'plugins {}',
        ]);
    }

    /**
     * @param list<string> $argv
     */
    private function execute(
        array $argv,
        FakeFilesystem $filesystem,
        ?FakeProcessRunner $process = null,
        ?RecordingPrinter $recorder = null,
    ): int {
        $recorder ??= RecordingPrinter::create();

        $context = new Context(
            output: $recorder->printer(),
            filesystem: $filesystem,
            process: $process ?? new FakeProcessRunner(),
            workingDirectory: self::ROOT,
            frameworkRoot: '/framework',
            catalog: new PackageCatalog($filesystem, '/framework'),
        );

        return (new Application($context))->run($argv);
    }
}
