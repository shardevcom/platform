<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Plugins;

/**
 * Boundary: a plugin as declared by its package's `plugin.json`.
 *
 * A platform package becomes a plugin the moment it ships this manifest: it is
 * what ties the API resource (actions, abilities) to the web views each guard
 * renders. The CLI reads it; it never invents it, so the developer-facing list
 * cannot drift from what the package actually exposes.
 */
final class PluginManifest
{
    /**
     * @param list<string>         $actions resource actions (view, create, ...)
     * @param array<string,string> $guards  guard => mode (read, write, full)
     */
    public function __construct(
        public readonly string $name,
        public readonly string $title,
        public readonly string $resource,
        public readonly array $actions,
        public readonly array $guards,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $name = $data['name'] ?? null;
        $resource = $data['resource'] ?? null;

        if (! is_string($name) || $name === '' || ! is_string($resource) || $resource === '') {
            return null;
        }

        $actions = [];

        foreach ((array) ($data['actions'] ?? []) as $action) {
            if (is_string($action) && $action !== '') {
                $actions[] = $action;
            }
        }

        $guards = [];

        foreach ((array) ($data['guards'] ?? []) as $guard => $mode) {
            if (is_string($guard) && $guard !== '') {
                $guards[$guard] = is_string($mode) && $mode !== '' ? $mode : 'full';
            }
        }

        $title = $data['title'] ?? null;

        return new self(
            name: $name,
            title: is_string($title) && $title !== '' ? $title : ucfirst($name),
            resource: $resource,
            actions: $actions,
            guards: $guards,
        );
    }

    /**
     * Permission strings the resource exposes, in the platform's vocabulary.
     *
     * @return list<string>
     */
    public function abilities(): array
    {
        return array_map(fn (string $action): string => $this->resource.'.'.$action, $this->actions);
    }

    public function guardSummary(): string
    {
        $parts = [];

        foreach ($this->guards as $guard => $mode) {
            $parts[] = $guard.' ('.$mode.')';
        }

        return $parts === [] ? '—' : implode(', ', $parts);
    }
}
