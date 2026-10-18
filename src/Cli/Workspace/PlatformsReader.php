<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace;

use Shardevcom\Cli\Filesystem\Filesystem;

/**
 * Reads the platform keys the API exposes from its `config/platforms.php`.
 *
 * The API owns the guard list; the workspace manifest mirrors it so a client
 * always serves exactly the guards the API defines. The file is parsed with a
 * regex instead of `require` because it calls `env()` and references app
 * classes, neither of which exist in the CLI process.
 */
final class PlatformsReader
{
    public function __construct(private readonly Filesystem $filesystem) {}

    /**
     * @return list<string>
     */
    public function keys(string $apiPath): array
    {
        $file = rtrim($apiPath, '/').'/config/platforms.php';

        if (! $this->filesystem->isFile($file)) {
            return [];
        }

        $config = $this->filesystem->read($file);
        preg_match_all("/'key'\s*=>\s*'([a-z0-9_-]+)'/i", $config, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }
}
