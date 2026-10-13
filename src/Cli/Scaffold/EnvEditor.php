<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Scaffold;

use Shardevcom\Cli\Filesystem\Filesystem;

/**
 * Boundary: the only writer of a generated project's .env.
 *
 * Set-key semantics (replace, uncomment or append) instead of blind regex
 * rewrites, so re-running the installer on an existing .env is idempotent.
 */
final class EnvEditor
{
    public function __construct(private readonly Filesystem $filesystem)
    {
    }

    /**
     * @param array<string, string> $values
     */
    public function write(string $envFile, array $values, bool $overwrite = true): void
    {
        $contents = $this->filesystem->isFile($envFile)
            ? $this->filesystem->read($envFile)
            : '';

        foreach ($values as $key => $value) {
            $contents = $this->setKey($contents, $key, $value, $overwrite);
        }

        $this->filesystem->write($envFile, $contents);
    }

    private function setKey(string $contents, string $key, string $value, bool $overwrite): string
    {
        $line = $key.'='.$value;
        $pattern = '/^#?\s*'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $contents) === 1) {
            return $overwrite
                ? (string) preg_replace($pattern, $line, $contents)
                : $contents;
        }

        return rtrim($contents, "\n")."\n".$line."\n";
    }
}