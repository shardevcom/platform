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
 * Acceptance for `platform plugin ...`.
 *
 * A plugin is an installed package that ships a `plugin.json`; the list must be
 * derived from those manifests (never a hardcoded registry) so the CLI, the API
 * and the SPA cannot disagree about what is installed.
 */
final class PluginCommandTest extends TestCase
{
    private const PRODUCT = '/framework/packages/shardevcom/product';

    public function test_lists_the_plugins_declared_by_the_installed_packages(): void
    {
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'plugin', 'list'], $this->filesystem(), $recorder);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('product', $recorder->stdout());
        $this->assertStringContainsString('products', $recorder->stdout());
        $this->assertStringContainsString('web (read)', $recorder->stdout());
        $this->assertStringContainsString('admin (full)', $recorder->stdout());
    }

    public function test_skips_packages_without_a_plugin_manifest(): void
    {
        $recorder = RecordingPrinter::create();

        $this->execute(['platform', 'plugin', 'list'], $this->filesystem(), $recorder);

        $this->assertStringNotContainsString('core', $recorder->stdout());
    }

    public function test_reports_when_no_plugin_is_installed(): void
    {
        $recorder = RecordingPrinter::create();

        $filesystem = new FakeFilesystem([
            '/framework/packages/shardevcom/core/composer.json' => '{"name":"shardevcom/core"}',
        ]);

        $exit = $this->execute(['platform', 'plugin', 'list'], $filesystem, $recorder);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('No plugin manifests found', $recorder->stdout());
    }

    public function test_shows_one_plugin_with_abilities_and_guard_routes(): void
    {
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'plugin', 'show', 'product'], $this->filesystem(), $recorder);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Products (product)', $recorder->stdout());
        $this->assertStringContainsString('products.view', $recorder->stdout());
        $this->assertStringContainsString('products.import', $recorder->stdout());
        $this->assertStringContainsString('read', $recorder->stdout());
        $this->assertStringContainsString('products', $recorder->stdout());
    }

    public function test_shows_one_plugin_as_json(): void
    {
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'plugin', 'show', 'product', '--json'], $this->filesystem(), $recorder);

        $this->assertSame(0, $exit);
        $payload = json_decode(trim($recorder->stdout()), true);

        $this->assertSame('products', $payload['resource']);
        $this->assertContains('products.view', $payload['abilities']);
        $this->assertSame('full', $payload['guards']['admin']['mode']);
        $this->assertSame('products', $payload['guards']['admin']['path']);
    }

    public function test_lists_the_plugins_as_json(): void
    {
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'plugin', 'list', '--json'], $this->filesystem(), $recorder);

        $this->assertSame(0, $exit);
        $payload = json_decode(trim($recorder->stdout()), true);

        $this->assertCount(1, $payload);
        $this->assertSame('product', $payload[0]['name']);
    }

    public function test_reports_an_unknown_plugin(): void
    {
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'plugin', 'show', 'billing'], $this->filesystem(), $recorder);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Unknown plugin', $recorder->stderr());
        $this->assertStringContainsString('product', $recorder->stderr());
    }

    public function test_show_requires_a_plugin_name(): void
    {
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'plugin', 'show'], $this->filesystem(), $recorder);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Missing plugin name', $recorder->stderr());
    }

    public function test_rejects_an_unknown_plugin_sub_command(): void
    {
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'plugin', 'wat'], $this->filesystem(), $recorder);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Unknown plugin sub-command', $recorder->stderr());
    }

    private function filesystem(): FakeFilesystem
    {
        return new FakeFilesystem([
            '/framework/packages/shardevcom/core/composer.json' => '{"name":"shardevcom/core"}',
            self::PRODUCT.'/composer.json' => <<<'JSON'
            {
                "name": "shardevcom/product",
                "autoload": { "psr-4": { "Shardevcom\\Product\\": "src/" } },
                "extra": { "platform": { "provider": "Shardevcom\\Product\\ProductServiceProvider" } }
            }
            JSON,
            self::PRODUCT.'/plugin.json' => <<<'JSON'
            {
                "name": "product",
                "title": "Products",
                "resource": "products",
                "actions": ["view", "list", "create", "update", "delete", "import", "export"],
                "guards": {
                    "web": { "mode": "read", "path": "products" },
                    "employee": { "mode": "write", "path": "products" },
                    "admin": { "mode": "full", "path": "products" }
                }
            }
            JSON,
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
            workingDirectory: '/framework',
            frameworkRoot: '/framework',
            catalog: new PackageCatalog($filesystem, '/framework'),
        );

        return (new Application($context))->run($argv);
    }
}
