<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Plugins;

/**
 * Boundary: how one guard consumes a plugin.
 *
 * `mode` is the coarse capability tier (read/write/full) that mirrors the
 * token abilities; `path` is the route the web module mounts for that guard
 * (e.g. `products` -> `/products`), so `plugin show` can tell a developer
 * where the view lands without opening the SPA.
 */
final class PluginGuard
{
    public function __construct(
        public readonly string $name,
        public readonly string $mode,
        public readonly ?string $path,
    ) {}

    public function summary(): string
    {
        return $this->name.' ('.$this->mode.')';
    }

    /**
     * @return array{mode: string, path: string|null}
     */
    public function toArray(): array
    {
        return ['mode' => $this->mode, 'path' => $this->path];
    }
}
