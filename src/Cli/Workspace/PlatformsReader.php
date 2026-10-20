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
        return array_keys($this->visibility($apiPath));
    }

    /**
     * Guard name => whether its client view is public, as declared by the API.
     *
     * Each platform block runs from its `'key' => '...'` line to the next one;
     * `'public' => true` anywhere inside that block marks the guard public.
     *
     * @return array<string, bool>
     */
    public function visibility(string $apiPath): array
    {
        $file = rtrim($apiPath, '/').'/config/platforms.php';

        if (! $this->filesystem->isFile($file)) {
            return [];
        }

        $config = $this->filesystem->read($file);
        preg_match_all("/'key'\s*=>\s*'([a-z0-9_-]+)'/i", $config, $matches, PREG_OFFSET_CAPTURE);

        $visibility = [];
        $found = $matches[1] ?? [];

        foreach ($found as $index => $match) {
            $key = $match[0];
            $start = $match[1];
            $end = $found[$index + 1][1] ?? strlen($config);
            $block = substr($config, $start, $end - $start);

            $visibility[$key] = match (true) {
                preg_match("/'public'\s*=>\s*true/i", $block) === 1 => true,
                preg_match("/'public'\s*=>\s*false/i", $block) === 1 => false,
                default => $key === 'web',
            };
        }

        return $visibility;
    }
}
