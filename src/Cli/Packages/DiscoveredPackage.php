<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Packages;

/**
 * Boundary: one platform package, as described by its own composer.json.
 *
 * Everything the installer needs to wire a package (namespace, autoload path,
 * provider, dependencies) is read from the package manifest instead of being
 * hardcoded in the CLI, so adding a package never means editing the installer.
 */
final class DiscoveredPackage
{
    /**
     * @param array<string, string> $psr4   namespace prefix => autoload sub-path ("src/")
     * @param list<string>         $requires composer package names this package depends on
     */
    public function __construct(
        public readonly string $vendor,
        public readonly string $package,
        public readonly string $name,
        public readonly string $path,
        public readonly string $relativePath,
        public readonly ?string $provider,
        public readonly array $psr4 = [],
        public readonly array $requires = [],
    ) {}

    public function key(): string
    {
        return $this->vendor.'/'.$this->package;
    }

    /**
     * @param list<string> $namespaces
     */
    public function hasNamespace(array $namespaces): bool
    {
        return array_intersect($namespaces, array_keys($this->psr4)) !== [];
    }

    /**
     * @return list<string>
     */
    public function namespaces(): array
    {
        return array_keys($this->psr4);
    }

    public function hasProvider(): bool
    {
        return $this->provider !== null && $this->provider !== '';
    }

    public function isPlatformBootstrapper(): bool
    {
        if (! $this->hasProvider()) {
            return false;
        }

        $short = substr((string) strrchr('\\'.$this->provider, '\\'), 1);

        return $short === 'PlatformServiceProvider';
    }
}