<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor;

use Shardevcom\Cli\Filesystem\Filesystem;

/**
 * Project file checks: .env, autoloader, writable directories and the
 * platform config file.
 */
final class ProjectSuite implements CheckSuite
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $projectRoot,
    ) {}

    public function name(): string
    {
        return 'Project files';
    }

    public function run(): array
    {
        return [
            $this->presence('.env', '.env file'),
            $this->presence('vendor/autoload.php', 'Autoloader'),
            $this->presence('config/platform.php', 'Platform config'),
            $this->writable('bootstrap/cache', 'Writable cache directory'),
            $this->writable('storage', 'Writable storage directory'),
            $this->writable('database', 'Writable database directory'),
        ];
    }

    private function presence(string $relative, string $label): CheckResult
    {
        $found = $this->filesystem->exists($this->projectRoot.'/'.$relative);

        return CheckResult::of(
            $label,
            $found,
            $found ? $relative : 'Missing: '.$relative
        );
    }

    private function writable(string $relative, string $label): CheckResult
    {
        $path = $this->projectRoot.'/'.$relative;
        $writable = $this->filesystem->isDirectory($path) && $this->filesystem->isWritable($path);

        return CheckResult::of(
            $label,
            $writable,
            $writable ? $relative : 'Not writable: '.$relative
        );
    }
}