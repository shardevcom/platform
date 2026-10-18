<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use Shardevcom\Cli\Command\DoctorCommand;
use Shardevcom\Cli\Context;
use Shardevcom\Cli\Doctor\CheckResult;
use Shardevcom\Cli\Doctor\CheckSuite;
use Shardevcom\Cli\Doctor\DatabaseSuite;
use Shardevcom\Cli\Doctor\PackagesSuite;
use Shardevcom\Cli\Doctor\ProjectSuite;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Packages\PackageCatalog;
use Tests\Unit\Cli\Support\FakeFilesystem;
use Tests\Unit\Cli\Support\FakeProcessRunner;
use Tests\Unit\Cli\Support\RecordingPrinter;

/**
 * Acceptance for `platform doctor`.
 *
 * The command itself is asserted through injected suites (its real contract:
 * render everything, never abort on a broken suite, exit non-zero on failure),
 * and each suite is asserted against the filesystem/process boundaries.
 */
final class DoctorCommandTest extends TestCase
{
    public function test_renders_every_check_and_exits_non_zero_when_one_fails(): void
    {
        $recorder = RecordingPrinter::create();

        $exit = $this->execute($recorder, [
            $this->suiteReturning('Environment', [CheckResult::pass('dummy ok', 'detail')]),
            $this->suiteReturning('Packages', [CheckResult::fail('dummy broken', 'why')]),
        ]);

        $output = $recorder->stdout();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Environment', $output);
        $this->assertStringContainsString('Packages', $output);
        $this->assertStringContainsString('dummy ok', $output);
        $this->assertStringContainsString('dummy broken', $output);
        $this->assertStringContainsString('why', $output);
        $this->assertStringContainsString('1 of 2 checks failed', $output);
    }

    public function test_exits_zero_when_every_check_passes(): void
    {
        $recorder = RecordingPrinter::create();

        $exit = $this->execute($recorder, [$this->suiteReturning('Environment', [CheckResult::pass('all good')])]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('all good', $recorder->stdout());
    }

    public function test_includes_client_requirements_when_a_workspace_is_present(): void
    {
        $recorder = RecordingPrinter::create();
        $filesystem = new FakeFilesystem([
            '/app/platform.json' => '{"name":"demo","platform":"0.1.0","components":{'
                .'"api":{"type":"api","path":"api","guards":[],"enabled":true},'
                .'"web":{"type":"web","path":"web","guards":["admin"],"enabled":true}}}',
            '/app/api/composer.json' => '{"require":{"laravel/framework":"^12.0"}}',
        ]);

        $context = new Context(
            output: $recorder->printer(),
            filesystem: $filesystem,
            process: new FakeProcessRunner(),
            workingDirectory: '/app',
            frameworkRoot: '/framework',
            catalog: new PackageCatalog($filesystem, '/app'),
        );

        (new DoctorCommand($context))->execute(new Input(), $context);

        $this->assertStringContainsString('Client requirements', $recorder->stdout());
        $this->assertStringContainsString('web: Node.js', $recorder->stdout());
    }

    public function test_a_suite_that_throws_does_not_hide_the_rest(): void
    {
        $recorder = RecordingPrinter::create();
        $exploding = new class implements CheckSuite {
            public function name(): string
            {
                return 'Exploding';
            }

            public function run(): array
            {
                throw new \RuntimeException('boom');
            }
        };

        $exit = $this->execute($recorder, [
            $exploding,
            $this->suiteReturning('Healthy', [CheckResult::pass('still checked')]),
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('still checked', $recorder->stdout());
        $this->assertStringContainsString('boom', $recorder->stdout());
        $this->assertStringContainsString('Exploding', $recorder->stdout());
    }

    public function test_packages_suite_derives_its_expectations_from_the_catalog(): void
    {
        $filesystem = $this->healthyProject();
        $suite = new PackagesSuite(
            $filesystem,
            new PackageCatalog($filesystem, '/app'),
            '/app'
        );

        $results = $suite->run();
        $this->assertContainsOnlyInstancesOf(CheckResult::class, $results);
        $this->assertTrue($this->passed($results));
        $this->assertStringContainsString('1 discovered in /app/packages', $results[0]->detail);
    }

    public function test_packages_suite_reports_a_package_without_provider(): void
    {
        $filesystem = new FakeFilesystem([
            '/app/packages/shardevcom/core/composer.json' => '{"name":"shardevcom/core"}',
        ]);

        $results = (new PackagesSuite(
            $filesystem,
            new PackageCatalog($filesystem, '/app'),
            '/app'
        ))->run();

        $this->assertFalse($this->passed($results));
        $this->assertStringContainsString('Missing provider: shardevcom/core', $this->detailOf($results, 'Package providers'));
        $this->assertStringContainsString(
            'No PlatformServiceProvider found',
            $this->detailOf($results, 'Platform bootstrapper')
        );
    }

    public function test_project_suite_uses_the_filesystem_for_writability(): void
    {
        $healthy = $this->healthyProject();
        $this->assertTrue($this->passed((new ProjectSuite($healthy, '/app'))->run()));

        $broken = $this->healthyProject(['/app/storage']);
        $results = (new ProjectSuite($broken, '/app'))->run();

        $this->assertFalse($this->passed($results));
        $this->assertStringContainsString('Not writable: storage', $this->detailOf($results, 'Writable storage directory'));
    }

    public function test_database_suite_connects_to_the_configured_sqlite_file(): void
    {
        $root = sys_get_temp_dir().'/platform-doctor-'.getmypid();
        @mkdir($root.'/database', 0777, true);
        file_put_contents($root.'/database/database.sqlite', '');

        try {
            $filesystem = new FakeFilesystem([$root.'/database/database.sqlite' => '']);

            $results = (new DatabaseSuite($filesystem, $root))->run();

            $this->assertTrue($this->passed($results));
        } finally {
            @unlink($root.'/database/database.sqlite');
            @rmdir($root.'/database');
            @rmdir($root);
        }
    }

    /**
     * @param list<CheckSuite> $suites
     */
    private function execute(RecordingPrinter $recorder, array $suites): int
    {
        $filesystem = $this->healthyProject();
        $context = new Context(
            output: $recorder->printer(),
            filesystem: $filesystem,
            process: new FakeProcessRunner(),
            workingDirectory: '/app',
            frameworkRoot: '/framework',
            catalog: new PackageCatalog($filesystem, '/app'),
        );

        return (new DoctorCommand($context, $suites))->execute(new Input(), $context);
    }

    /**
     * @param list<CheckResult> $results
     */
    private function suiteReturning(string $name, array $results): CheckSuite
    {
        return new class($name, $results) implements CheckSuite {
            /**
             * @param list<CheckResult> $results
             */
            public function __construct(
                private readonly string $name,
                private readonly array $results,
            ) {}

            public function name(): string
            {
                return $this->name;
            }

            public function run(): array
            {
                return $this->results;
            }
        };
    }

    /**
     * @param list<string> $unwritable
     */
    private function healthyProject(array $unwritable = []): FakeFilesystem
    {
        return new FakeFilesystem(
            files: [
                '/app/composer.json' => '{"require":{"laravel/framework":"^12.0"},"autoload":{"psr-4":{"App\\\\":"app/","Shardevcom\\\\Core\\\\":"packages/shardevcom/core/src/"}}}',
                '/app/vendor/autoload.php' => '<?php',
                '/app/.env' => "APP_KEY=base64:x\nDB_CONNECTION=sqlite\n",
                '/app/config/platform.php' => '<?php',
                '/app/app/Http/Controllers/Controller.php' => '<?php',
                '/app/database/database.sqlite' => '',
                '/app/packages/shardevcom/core/composer.json' => '{"name":"shardevcom/core","extra":{"platform":{"provider":"Shardevcom\\\\Core\\\\PlatformServiceProvider"}}}',
                '/app/packages/shardevcom/core/src/PlatformServiceProvider.php' => '<?php',
            ],
            directories: ['/app/bootstrap/cache', '/app/storage', '/app/database'],
            unwritable: $unwritable,
        );
    }

    /**
     * @param list<CheckResult> $results
     */
    private function passed(array $results): bool
    {
        return array_reduce(
            $results,
            static fn (bool $carry, CheckResult $result): bool => $carry && $result->passed,
            true
        );
    }

    /**
     * @param list<CheckResult> $results
     */
    private function detailOf(array $results, string $check): string
    {
        foreach ($results as $result) {
            if ($result->label === $check) {
                return (string) $result->detail;
            }
        }

        $this->fail("Check '{$check}' was not run");
    }
}