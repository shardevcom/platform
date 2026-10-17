<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace;

use Shardevcom\Cli\Filesystem\Filesystem;

/**
 * Boundary: a resolved workspace (root directory + its manifest).
 *
 * Location walks up from the working directory until it finds platform.json,
 * so every workspace command works from the root or from inside any component.
 */
final class Workspace
{
    public function __construct(
        public readonly string $root,
        public readonly WorkspaceManifest $manifest,
    ) {}

    public static function locate(Filesystem $filesystem, string $from, string $frameworkRoot): ?self
    {
        $directory = rtrim($from, '/');
        $stop = rtrim($frameworkRoot, '/');

        while (true) {
            if ($filesystem->isFile($directory.'/'.WorkspaceManifest::FILE)) {
                return new self($directory, WorkspaceManifest::load($filesystem, $directory));
            }

            $parent = dirname($directory);

            if ($directory === '' || $directory === '/' || $directory === $stop || $parent === $directory) {
                return null;
            }

            $directory = $parent;
        }
    }

    public function path(string $relative): string
    {
        return rtrim($this->root, '/').'/'.ltrim($relative, '/');
    }

    public function componentPath(string $name): string
    {
        return $this->path($this->manifest->component($name)->path);
    }
}
