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
 * each client mirrors them with one app/flavor per guard. Every guard carries a
 * visibility flag: a public guard renders its app directly, while a private one
 * starts on the login page.
 */
final class Component
{
    /**
     * @param array<string, bool>|list<string> $guards guard name => whether its client view is public
     */
    public readonly array $guards;

    /**
     * @param array<string, bool>|list<string> $guards
     */
    public function __construct(
        public readonly string $name,
        public readonly ComponentType $type,
        public readonly string $path,
        array $guards = [],
        public readonly bool $enabled = true,
    ) {
        $this->guards = self::normalize($guards);
    }

    /**
     * @param array<string, bool>|list<string> $guards
     */
    public static function create(string $name, array $guards = []): self
    {
        $type = ComponentType::fromName($name);

        return new self(
            name: $name,
            type: $type,
            path: $name,
            guards: self::normalize($guards === [] ? $type->defaultGuards() : $guards),
        );
    }

    public function primaryGuard(): ?string
    {
        return array_key_first($this->guards);
    }

    public function serves(string $guard): bool
    {
        return array_key_exists($guard, $this->guards);
    }

    public function isPublic(string $guard): bool
    {
        return $this->guards[$guard] ?? false;
    }

    /**
     * @return list<string>
     */
    public function guardNames(): array
    {
        return array_keys($this->guards);
    }

    /**
     * @param array<string, bool>|list<string> $guards
     */
    public function withGuards(array $guards): self
    {
        return new self($this->name, $this->type, $this->path, self::normalize($guards), $this->enabled);
    }

    public function withEnabled(bool $enabled): self
    {
        return new self($this->name, $this->type, $this->path, $this->guards, $enabled);
    }

    /**
     * @return array{type: string, path: string, guards: array<string, bool>, enabled: bool}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'path' => $this->path,
            'guards' => $this->guards === [] ? [] : array_map(
                static fn (bool $public): array => ['public' => $public],
                $this->guards,
            ),
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
     * Reads the guard map, accepting the legacy list shape (and the even older
     * single `guard` key) so older platform.json files keep loading.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, bool>
     */
    private static function guardsFrom(array $data): array
    {
        $guards = $data['guards'] ?? null;

        if (is_array($guards) && $guards !== []) {
            return self::normalize($guards);
        }

        $legacy = $data['guard'] ?? null;

        return is_string($legacy) && $legacy !== '' ? self::normalize([$legacy]) : [];
    }

    /**
     * Normalises both the object shape (`{"web": {"public": true}}`) and the
     * legacy list (`["web"]`) into guard => public. The conventional `web`
     * guard defaults to public; every other guard defaults to private.
     *
     * @param array<mixed> $guards
     *
     * @return array<string, bool>
     */
    private static function normalize(array $guards): array
    {
        $normalized = [];

        foreach ($guards as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = is_array($value)
                    ? (bool) ($value['public'] ?? self::defaultVisibility($key))
                    : (is_bool($value) ? $value : self::defaultVisibility($key));

                continue;
            }

            $name = (string) $value;
            $normalized[$name] = self::defaultVisibility($name);
        }

        return $normalized;
    }

    private static function defaultVisibility(string $guard): bool
    {
        return $guard === 'web';
    }
}
