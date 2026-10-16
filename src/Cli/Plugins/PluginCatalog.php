<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Plugins;

use Shardevcom\Cli\Filesystem\Filesystem;
use Shardevcom\Cli\Packages\PackageCatalog;

/**
 * Boundary: the plugins among the discovered packages.
 *
 * Discovery reuses the same PackageCatalog the installer uses, so `plugin list`
 * reports exactly the packages the project really has — a package without a
 * `plugin.json` is simply not a plugin and is skipped.
 */
final class PluginCatalog
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly PackageCatalog $packages,
    ) {}

    /**
     * @return list<PluginManifest>
     */
    public function all(): array
    {
        $plugins = [];

        foreach ($this->packages->packages() as $package) {
            $data = $this->filesystem->readJson($package->path.'/plugin.json');

            if ($data === null) {
                continue;
            }

            $manifest = PluginManifest::fromArray($data);

            if ($manifest !== null) {
                $plugins[] = $manifest;
            }
        }

        usort($plugins, static fn (PluginManifest $a, PluginManifest $b): int => $a->name <=> $b->name);

        return $plugins;
    }

    public function count(): int
    {
        return count($this->all());
    }

    public function find(string $name): ?PluginManifest
    {
        foreach ($this->all() as $plugin) {
            if ($plugin->name === $name) {
                return $plugin;
            }
        }

        return null;
    }
}
