<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use Shardevcom\Cli\Workspace\WorkspaceScript;

/**
 * The workspace control shell (`platform.sh`) is generated from a single
 * template; these tests pin its contract (a portable bash script that reads the
 * manifest) and its syntax.
 */
final class WorkspaceScriptTest extends TestCase
{
    public function test_is_a_portable_bash_script_that_reads_the_manifest(): void
    {
        $script = WorkspaceScript::render();

        $this->assertStringStartsWith('#!/usr/bin/env bash', $script);
        $this->assertStringContainsString('platform.json', $script);
        $this->assertStringContainsString('set -uo pipefail', $script);
    }

    public function test_exposes_the_control_commands(): void
    {
        $script = WorkspaceScript::render();

        foreach ([
            'up', 'api', 'web', 'mobile', 'desktop',
            'stop', 'restart', 'status', 'logs', 'build', 'install', 'doctor', 'fix',
        ] as $command) {
            $this->assertMatchesRegularExpression(
                '/^\s+'.preg_quote($command, '/').'(?:\||\))/m',
                $script,
                "missing router entry for: {$command}"
            );
        }
    }

    public function test_derives_components_and_guards_from_the_manifest(): void
    {
        $script = WorkspaceScript::render();

        $this->assertStringContainsString('component_names()', $script);
        $this->assertStringContainsString('guard_list_for()', $script);
        $this->assertStringContainsString('app_for_guard()', $script);
        $this->assertStringNotContainsString('apps/admin', $script);
    }

    public function test_is_valid_bash_syntax(): void
    {
        if (trim((string) shell_exec('command -v bash')) === '') {
            $this->markTestSkipped('bash is not available.');
        }

        $file = tempnam(sys_get_temp_dir(), 'platform-script');
        $this->assertNotFalse($file);
        file_put_contents($file, WorkspaceScript::render());

        $output = [];
        $status = 0;
        exec('bash -n '.escapeshellarg($file).' 2>&1', $output, $status);
        @unlink($file);

        $this->assertSame(0, $status, implode("\n", $output));
    }
}
