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
     * Every plugin with the manifest file it was read from. Commands that edit
     * a `plugin.json` need the path, which the manifest alone does not carry.
     *
     * @return list<array{manifest: PluginManifest, file: string}>
     */
    public function discover(): array
    {
        $plugins = [];

        foreach ($this->packages->packages() as $package) {
            $file = $package->path.'/plugin.json';
            $data = $this->filesystem->readJson($file);

            if ($data === null) {
                continue;
            }

            $manifest = PluginManifest::fromArray($data);

            if ($manifest !== null) {
                $plugins[] = ['manifest' => $manifest, 'file' => $file];
            }
        }

        usort($plugins, static fn (array $a, array $b): int => $a['manifest']->name <=> $b['manifest']->name);

        return $plugins;
    }

    /**
     * @return list<PluginManifest>
     */
    public function all(): array
    {
        return array_map(static fn (array $plugin): PluginManifest => $plugin['manifest'], $this->discover());
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
