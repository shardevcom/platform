<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace;

/**
 * One member of a workspace: the API or a client.
 *
 * A component is pure data (name, type, relative path, guards, enabled). It is
 * stored in platform.json and read back by every workspace command, so the
 * commands never guess where a component lives or which guards it serves.
 *
 * A component serves N guards: the API declares the platforms it exposes and
 * each client mirrors them with one app/flavor per guard. A single guard is
 * just a list of one, so nothing downstream needs a special case.
 */
final class Component
{
    /**
     * @param list<string> $guards guards this component serves (empty for the API when unknown)
     */
    public function __construct(
        public readonly string $name,
        public readonly ComponentType $type,
        public readonly string $path,
        public readonly array $guards = [],
        public readonly bool $enabled = true,
    ) {}

    /**
     * @param list<string> $guards
     */
    public static function create(string $name, array $guards = []): self
    {
        $type = ComponentType::fromName($name);

        return new self(
            name: $name,
            type: $type,
            path: $name,
            guards: $guards === [] ? $type->defaultGuards() : array_values($guards),
        );
    }

    public function primaryGuard(): ?string
    {
        return $this->guards[0] ?? null;
    }

    public function serves(string $guard): bool
    {
        return in_array($guard, $this->guards, true);
    }

    /**
     * @param list<string> $guards
     */
    public function withGuards(array $guards): self
    {
        return new self($this->name, $this->type, $this->path, array_values($guards), $this->enabled);
    }

    public function withEnabled(bool $enabled): self
    {
        return new self($this->name, $this->type, $this->path, $this->guards, $enabled);
    }

    /**
     * @return array{type: string, path: string, guards: list<string>, enabled: bool}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'path' => $this->path,
            'guards' => array_values($this->guards),
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
            guards: self::guardsFrom($data),
            enabled: (bool) ($data['enabled'] ?? true),
        );
    }

    /**
     * Reads the guard list, accepting the legacy single `guard` key so older
     * platform.json files keep loading.
     *
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    private static function guardsFrom(array $data): array
    {
        $guards = $data['guards'] ?? null;

        if (is_array($guards)) {
            return array_values(array_map(
                static fn (mixed $guard): string => (string) $guard,
                $guards
            ));
        }

        $legacy = $data['guard'] ?? null;

        return is_string($legacy) && $legacy !== '' ? [$legacy] : [];
    }
}
