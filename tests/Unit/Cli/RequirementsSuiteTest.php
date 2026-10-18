<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use Shardevcom\Cli\Context;
use Shardevcom\Cli\Doctor\CheckResult;
use Shardevcom\Cli\Doctor\RequirementsSuite;
use Shardevcom\Cli\Workspace\Component;
use Shardevcom\Cli\Workspace\ComponentType;
use Shardevcom\Cli\Workspace\DriverRegistry;
use Shardevcom\Cli\Workspace\Workspace;
use Shardevcom\Cli\Workspace\WorkspaceManifest;
use Tests\Unit\Cli\Support\FakeFilesystem;
use Tests\Unit\Cli\Support\FakeProcessRunner;
use Tests\Unit\Cli\Support\RecordingPrinter;

/**
 * Acceptance for the doctor's client-requirements suite.
 *
 * The suite must derive its checks from the installed components (never a
 * hardcoded list) and turn a missing tool into an actionable suggestion.
 */
final class RequirementsSuiteTest extends TestCase
{
    public function test_reports_a_present_toolchain_as_passing_with_its_version(): void
    {
        $process = new FakeProcessRunner([
            ['needle' => 'node --version', 'output' => "v24.13.1\n"],
            ['needle' => 'npm --version', 'output' => "11.8.0\n"],
        ]);

        $results = $this->suite($this->workspace([Component::create('web')]), $process)->run();

        $this->assertTrue($this->passed($results));
        $this->assertStringContainsString('v24.13.1', $this->detailOf($results, 'web: Node.js'));
    }

    public function test_suggests_installation_when_a_tool_is_missing(): void
    {
        $process = new FakeProcessRunner([
            ['needle' => 'node --version', 'exitCode' => 127, 'output' => 'node: command not found'],
        ]);

        $results = $this->suite($this->workspace([Component::create('web')]), $process)->run();

        $this->assertFalse($this->passed($results));
        $this->assertStringContainsString('not on PATH', $this->detailOf($results, 'web: Node.js'));
        $this->assertStringContainsString('https://nodejs.org', $this->detailOf($results, 'web: Node.js'));
    }

    public function test_suggests_the_path_line_when_the_tool_is_installed_elsewhere(): void
    {
        $process = new FakeProcessRunner([
            ['needle' => 'flutter --version', 'exitCode' => 127, 'output' => 'flutter: command not found'],
        ]);
        $filesystem = new FakeFilesystem([
            '/opt/flutter/bin/flutter' => '#!/bin/sh',
            '/opt/flutter/bin/dart' => '#!/bin/sh',
        ]);

        $results = $this->suite($this->workspace([Component::create('mobile')]), $process, $filesystem)->run();

        $detail = $this->detailOf($results, 'mobile: Flutter SDK');
        $this->assertStringContainsString('found at /opt/flutter/bin/flutter', $detail);
        $this->assertStringContainsString('export PATH="/opt/flutter/bin:$PATH"', $detail);
    }

    public function test_only_enabled_clients_are_probed(): void
    {
        $process = new FakeProcessRunner();

        $workspace = $this->workspace([
            new Component('web', ComponentType::Web, 'web', ['admin'], false),
        ]);

        $results = $this->suite($workspace, $process)->run();

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->passed);
        $this->assertStringContainsString('No client components', $results[0]->detail);
        $this->assertFalse($process->ran('node --version'));
    }

    public function test_an_api_only_workspace_runs_no_client_checks(): void
    {
        $process = new FakeProcessRunner();

        $results = $this->suite($this->workspace([Component::create('api')]), $process)->run();

        $this->assertCount(1, $results);
        $this->assertSame('Clients', $results[0]->label);
        $this->assertTrue($results[0]->passed);
    }

    public function test_probes_run_inside_the_component_directory(): void
    {
        $process = new FakeProcessRunner();

        $this->suite($this->workspace([Component::create('desktop')]), $process)->run();

        $this->assertTrue($process->ranIn('java -version', '/ws/desktop'));
    }

    /**
     * @param list<Component> $components
     */
    private function workspace(array $components): Workspace
    {
        return new Workspace('/ws', WorkspaceManifest::make('demo', '0.1.0', $components));
    }

    private function suite(Workspace $workspace, FakeProcessRunner $process, ?FakeFilesystem $filesystem = null): RequirementsSuite
    {
        $filesystem ??= new FakeFilesystem();
        $context = new Context(
            output: RecordingPrinter::create()->printer(),
            filesystem: $filesystem,
            process: $process,
            workingDirectory: '/ws',
            frameworkRoot: '/framework',
        );

        return new RequirementsSuite($workspace, new DriverRegistry($context), $process, $filesystem);
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
