<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace;

use Shardevcom\Cli\Exception\CommandFailed;
use Shardevcom\Cli\Filesystem\Filesystem;

/**
 * Boundary: the workspace manifest (platform.json).
 *
 * This is the single source of truth the CLI reads to know what a generated
 * project contains. Commands iterate over it instead of hardcoding which
 * clients exist, exactly like `plugin.json` drives `platform plugin list`.
 */
final class WorkspaceManifest
{
    public const FILE = 'platform.json';

    /**
     * @param array<string, Component> $components
     */
    public function __construct(
        public readonly string $name,
        public readonly string $platform,
        private readonly array $components = [],
    ) {}

    /**
     * @param list<Component> $components
     */
    public static function make(string $name, string $platform, array $components = []): self
    {
        $indexed = [];

        foreach ($components as $component) {
            $indexed[$component->name] = $component;
        }

        return new self($name, $platform, $indexed);
    }

    public static function load(Filesystem $filesystem, string $root): self
    {
        $data = $filesystem->readJson(rtrim($root, '/').'/'.self::FILE);

        if ($data === null) {
            throw CommandFailed::create('The workspace manifest ('.self::FILE.') is missing or invalid.');
        }

        $components = [];

        foreach (($data['components'] ?? []) as $name => $component) {
            if (is_array($component)) {
                $components[(string) $name] = Component::fromArray((string) $name, $component);
            }
        }

        return new self(
            name: (string) ($data['name'] ?? basename($root)),
            platform: (string) ($data['platform'] ?? '0.1.0-dev'),
            components: $components,
        );
    }

    public function save(Filesystem $filesystem, string $root): void
    {
        $filesystem->writeJson(rtrim($root, '/').'/'.self::FILE, [
            'name' => $this->name,
            'platform' => $this->platform,
            'components' => array_map(
                static fn (Component $component): array => $component->toArray(),
                $this->components
            ),
        ]);
    }

    public function has(string $name): bool
    {
        return isset($this->components[$name]);
    }

    public function component(string $name): Component
    {
        return $this->components[$name] ?? throw CommandFailed::create(
            "Component '{$name}' is not part of this workspace."
        );
    }

    /**
     * @return array<string, Component>
     */
    public function components(): array
    {
        return $this->components;
    }

    /**
     * @return list<Component>
     */
    public function enabled(): array
    {
        return array_values(array_filter(
            $this->components,
            static fn (Component $component): bool => $component->enabled
        ));
    }

    public function with(Component $component): self
    {
        $components = $this->components;
        $components[$component->name] = $component;

        return new self($this->name, $this->platform, $components);
    }

    public function without(string $name): self
    {
        $components = $this->components;
        unset($components[$name]);

        return new self($this->name, $this->platform, $components);
    }
}
