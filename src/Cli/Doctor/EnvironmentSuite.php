<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor;

use Shardevcom\Cli\Filesystem\Filesystem;
use Shardevcom\Cli\Process\ProcessRunner;
use Shardevcom\Framework;

/**
 * Toolchain checks: PHP, Composer, Laravel constraint and framework identity.
 */
final class EnvironmentSuite implements CheckSuite
{
    private const PHP_CONSTRAINT = '8.3.0';

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly ProcessRunner $process,
        private readonly string $projectRoot,
    ) {}

    public function name(): string
    {
        return 'Toolchain';
    }

    public function run(): array
    {
        return [
            $this->phpVersion(),
            $this->composer(),
            $this->laravelVersion(),
            CheckResult::pass('Framework version', Framework::version()),
        ];
    }

    private function phpVersion(): CheckResult
    {
        $ok = version_compare(PHP_VERSION, self::PHP_CONSTRAINT, '>=');

        return CheckResult::of(
            'PHP version',
            $ok,
            sprintf('Current: %s, Required: ^%s', PHP_VERSION, self::PHP_CONSTRAINT)
        );
    }

    private function composer(): CheckResult
    {
        $result = $this->process->run('composer --version');
        $firstLine = strtok($result->output, "\n") ?: '';

        return CheckResult::of(
            'Composer',
            $result->contains('Composer version'),
            $result->successful() ? trim($firstLine) : 'composer not found on PATH'
        );
    }

    private function laravelVersion(): CheckResult
    {
        $composer = $this->filesystem->readJson($this->projectRoot.'/composer.json');
        $constraint = $composer['require']['laravel/framework'] ?? null;

        if (! is_string($constraint) || $constraint === '') {
            return CheckResult::fail('Laravel version', 'laravel/framework is not required by this project');
        }

        return CheckResult::of(
            'Laravel version',
            Framework::supportsLaravel(ltrim($constraint, '^~>= ')),
            "Found: {$constraint}"
        );
    }
}