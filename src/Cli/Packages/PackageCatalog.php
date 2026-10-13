<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Packages;

use Shardevcom\Cli\Config\PlatformConfig;
use Shardevcom\Cli\Filesystem\Filesystem;
use Shardevcom\Core\PackageManifest;

/**
 * Boundary: turns "what is on disk under packages/" into the wiring an
 * installed project needs (psr-4 map, composer requirements, providers).
 *
 * Discovery is delegated to the same PackageManifest the runtime uses, so the
 * CLI can never disagree with the application about what is installed. There
 * is deliberately no hardcoded list of official packages here.
 */
final class PackageCatalog
{
    /** @var list<DiscoveredPackage>|null */
    private ?array $packages = null;

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $projectRoot,
        private readonly string $packagesDir = PlatformConfig::DEFAULT_PACKAGES_DIR,
        private readonly string $vendorNamespace = PlatformConfig::DEFAULT_VENDOR_NAMESPACE,
    ) {}

    /**
     * @return list<DiscoveredPackage>
     */
    public function packages(): array
    {
        if ($this->packages !== null) {
            return $this->packages;
        }

        $packagesRoot = $this->packagesPath();
        $manifest = new PackageManifest(
            fn (string $path): array => $this->filesystem->directories($path),
            fn (string $directory): ?array => $this->filesystem->readJson($directory.'/composer.json'),
            $this->packagesDir,
            $this->vendorNamespace,
        );
        $discovered = $manifest->discover($this->projectRoot);

        $packages = [];

        foreach ($discovered as $package) {
            $path = $package['path'];
            $manifestData = $this->filesystem->readJson($path.'/composer.json') ?? [];
            $relativePath = $this->filesystem->relativePath($path, $packagesRoot);

            $psr4 = [];

            foreach ((array) ($manifestData['autoload']['psr-4'] ?? []) as $namespace => $subPath) {
                if (is_string($namespace) && is_string($subPath)) {
                    $psr4[rtrim($namespace, '\\').'\\'] = trim($subPath, '/');
                }
            }

            $packages[] = new DiscoveredPackage(
                vendor: $package['vendor'],
                package: $package['package'],
                name: $package['name'],
                path: $path,
                relativePath: $relativePath,
                provider: $package['provider'],
                psr4: $psr4,
                requires: array_values(array_filter(
                    array_keys((array) ($manifestData['require'] ?? [])),
                    static fn (mixed $name): bool => is_string($name)
                )),
            );
        }

        return $this->packages = $packages;
    }

    public function count(): int
    {
        return count($this->packages());
    }

    /**
     * The configured packages directory, relative to the project root.
     */
    public function packagesDir(): string
    {
        return trim($this->packagesDir, '/');
    }

    /**
     * The configured vendor namespace for packages created from now on.
     */
    public function vendorNamespace(): string
    {
        return $this->vendorNamespace;
    }

    /**
     * Absolute path of the packages directory this catalog reads.
     */
    public function packagesPath(): string
    {
        return rtrim($this->projectRoot, '/').'/'.trim($this->packagesDir, '/');
    }

    /**
     * @return list<string>
     */
    public function providers(): array
    {
        $providers = [];

        foreach ($this->packages() as $package) {
            if ($package->hasProvider()) {
                $providers[] = (string) $package->provider;
            }
        }

        return $providers;
    }

/**
     * Providers the generated project must register explicitly.
     *
     * The platform bootstrapper registers every other provider itself, so
     * listing them again would be a second place to forget to update. When no
     * bootstrapper exists, every provider has to be registered by hand.
     *
     * @return list<string>
     */
public function providersForBootstrap(): array
    {
        $bootstrapper = $this->platformBootstrapper();

        return $bootstrapper !== null ? [$bootstrapper] : $this->providers();
    }

    public function platformBootstrapper(): ?string
    {
        foreach ($this->packages() as $package) {
            if ($package->isPlatformBootstrapper()) {
                return (string) $package->provider;
            }
        }

        return null;
    }

    /**
     * PSR-4 map for the generated project's composer.json.
     *
     * @return array<string, string>
     */
    public function psr4Map(): array
    {
        $map = [];

        foreach ($this->packages() as $package) {
            foreach ($package->psr4 as $namespace => $subPath) {
                $map[$namespace] = $this->autoloadPath($package, $subPath);
            }
        }

        ksort($map);

        return $map;
    }

    /**
     * @return array<string, string>
     */
    public function requirements(string $constraint = '*'): array
    {
        $requirements = [];

        foreach ($this->packages() as $package) {
            $requirements[$package->name] = $constraint;
        }

        ksort($requirements);

        return $requirements;
    }

    /**
     * Directory (relative to the generated project) a package must be copied to.
     */
    public function targetPathFor(DiscoveredPackage $package): string
    {
        return trim($this->packagesDir, '/').'/'.$package->relativePath;
    }

    /**
     * Path repository glob matching every installed package, whatever its vendor.
     */
    public function repositoryGlob(): string
    {
        return trim($this->packagesDir, '/').'/*/*';
    }

    private function autoloadPath(DiscoveredPackage $package, string $subPath): string
    {
        return $this->targetPathFor($package).($subPath === '' ? '' : '/'.$subPath).'/';
    }
}