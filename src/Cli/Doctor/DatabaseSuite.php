<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor;

use Shardevcom\Cli\Filesystem\Filesystem;

/**
 * Database check: opens the configured sqlite file so a missing or corrupt
 * database fails here instead of on the first request.
 */
final class DatabaseSuite implements CheckSuite
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $projectRoot,
        private readonly string $connection = 'sqlite',
    ) {}

    public function name(): string
    {
        return 'Database';
    }

    public function run(): array
    {
        if ($this->connection !== 'sqlite') {
            return [CheckResult::pass('Database', "Skipped: connection '{$this->connection}' needs credentials")];
        }

        $file = $this->databaseFile();

        if (! $this->filesystem->isFile($file)) {
            return [CheckResult::fail('Database', 'Missing: '.basename($file))];
        }

        try {
            $pdo = new \PDO('sqlite:'.$file);
            $pdo->query('SELECT 1');
        } catch (\PDOException $exception) {
            return [CheckResult::fail('Database', $exception->getMessage())];
        }

        return [CheckResult::pass('Database', 'sqlite connected')];
    }

    private function databaseFile(): string
    {
        return rtrim($this->projectRoot, '/').'/database/database.sqlite';
    }
}