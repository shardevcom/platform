<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace;

/**
 * One member of a workspace: the API or a client.
 *
 * A component is pure data (name, type, relative path, guard, enabled). It is
 * stored in platform.json and read back by every workspace command, so the
 * commands never guess where a component lives or which guard it serves.
 */
final class Component
{
    public function __construct(
        public readonly string $name,
        public readonly ComponentType $type,
        public readonly string $path,
        public readonly ?string $guard = null,
        public readonly bool $enabled = true,
    ) {}

    public static function create(string $name, ?string $guard = null): self
    {
        $type = ComponentType::fromName($name);

        return new self($name, $type, $name, $guard ?? $type->defaultGuard());
    }

    /**
     * @return array{type: string, path: string, guard: string|null, enabled: bool}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'path' => $this->path,
            'guard' => $this->guard,
            'enabled' => $this->enabled,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(string $name, array $data): self
    {
        return new self(
            name: $name,
            type: ComponentType::fromName((string) ($data['type'] ?? $name)),
            path: (string) ($data['path'] ?? $name),
            guard: isset($data['guard']) ? (string) $data['guard'] : null,
            enabled: (bool) ($data['enabled'] ?? true),
        );
    }
}
