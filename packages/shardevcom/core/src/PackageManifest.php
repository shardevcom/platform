<?php

declare(strict_types=1);

namespace Shardevcom\Core;

use Closure;

/**
 * Reads platform package manifests (one composer.json per package dir).
 *
 * Boundary: package discovery without coupling the framework to the
 * Composer runtime API. Pure PHP — no Illuminate dependency.
 * Supports both legacy structure (packages/{pack}) and new structure (packages/{vendor}/{pack}).
 * Vendor namespace is configurable via PLATFORM_VENDOR_NAMESPACE env var (default: shardev).
 * Packages directory is configurable via PLATFORM_PACKAGES_DIR env var (default: packages).
 */
final class PackageManifest
{
    /** @var Closure(string): list<string> */
    private readonly Closure $listDirectories;

    /** @var Closure(string): array<string, mixed>|null */
    private readonly Closure $readManifest;

    /** @var string|null */
    private readonly ?string $packagesDir;

    /** @var string|null */
    private readonly ?string $vendorNamespace;

    /**
     * @param Closure(string): list<string>|null          $listDirectories directory lister seam
     * @param Closure(string): array<string, mixed>|null  $readManifest    manifest reader seam
     *
     * Both seams exist so the installer can discover packages through its own
     * filesystem abstraction instead of duplicating the layout rules below.
     */
    public function __construct(
        ?Closure $listDirectories = null,
        ?Closure $readManifest = null,
        ?string $packagesDir = null,
        ?string $vendorNamespace = null,
    ) {
        $this->listDirectories = $listDirectories ?? static function (string $path): array {
            $entries = glob(rtrim($path, '/').'/*', GLOB_ONLYDIR);

            return $entries === false ? [] : $entries;
        };

        $this->readManifest = $readManifest ?? static function (string $directory): ?array {
            $file = $directory.'/composer.json';

            if (! is_file($file)) {
                return null;
            }

            $decoded = json_decode((string) file_get_contents($file), true);

            return is_array($decoded) ? $decoded : null;
        };

        $this->packagesDir = $packagesDir;
        $this->vendorNamespace = $vendorNamespace;
    }

    /**
     * @return array<string, array{name: string, path: string, provider: class-string|null, vendor: string, package: string}> keyed by vendor/package
     */
    public function discover(string $projectRoot): array
    {
        $packagesPath = $this->getPackagesPath($projectRoot);
        $vendorNamespace = $this->getVendorNamespace();
        $found = [];

        foreach ($this->packageDirectories($packagesPath) as $dir) {
            $decoded = ($this->readManifest)($dir);

            if ($decoded === null) {
                continue;
            }

            $provider = $decoded['extra']['platform']['provider'] ?? null;

            $relativePath = $this->getRelativePath($packagesPath, $dir);
            [$vendor, $package] = $this->parseVendorPackage($relativePath, $vendorNamespace);

            $found["{$vendor}/{$package}"] = [
                'name' => (string) ($decoded['name'] ?? "{$vendor}/{$package}"),
                'path' => $dir,
                'provider' => is_string($provider) && $provider !== '' ? $provider : null,
                'vendor' => $vendor,
                'package' => $package,
            ];
        }

        ksort($found);

        return $found;
    }

    /**
     * @return list<class-string>
     */
    public function providers(string $projectRoot): array
    {
        $providers = [];

        foreach ($this->discover($projectRoot) as $package) {
            if ($package['provider'] !== null) {
                $providers[] = $package['provider'];
            }
        }

        return $providers;
    }

    /**
     * @return list<string>
     */
    private function packageDirectories(string $packagesPath): array
    {
        $dirs = [];
        $entries = ($this->listDirectories)($packagesPath);

        foreach ($entries as $entry) {
            $nested = ($this->listDirectories)($entry);

            // packages/{vendor}/{package} is the current layout; a directory
            // without children stays a candidate itself (legacy layout). Every
            // vendor contributes its packages, so several vendor directories
            // (e.g. shardevcom/* and a project's own acme/*) are all discovered.
            $dirs = [...$dirs, ...($nested === [] ? [$entry] : $nested)];
        }

        return array_values(array_unique($dirs));
    }

    private function getRelativePath(string $basePath, string $fullPath): string
    {
        $basePath = rtrim($basePath, '/');
        $fullPath = rtrim($fullPath, '/');

        if (str_starts_with($fullPath, $basePath . '/')) {
            return substr($fullPath, strlen($basePath) + 1);
        }

        return basename($fullPath);
    }

    private function parseVendorPackage(string $relativePath, string $vendorNamespace): array
    {
        $parts = explode('/', $relativePath);
        if (count($parts) >= 2) {
            return [$parts[0], $parts[1]];
        }

        // Legacy fallback - use configured vendor namespace
        return [$vendorNamespace, $parts[0]];
    }

    private function getPackagesPath(string $projectRoot): string
    {
        $packagesDir = $this->packagesDir
            ?? $_ENV['PLATFORM_PACKAGES_DIR']
            ?? getenv('PLATFORM_PACKAGES_DIR')
            ?: 'packages';

        return rtrim($projectRoot, '/') . '/' . ltrim((string) $packagesDir, '/');
    }

    private function getVendorNamespace(): string
    {
        $namespace = $this->vendorNamespace
            ?? $_ENV['PLATFORM_VENDOR_NAMESPACE']
            ?? getenv('PLATFORM_VENDOR_NAMESPACE');

        return is_string($namespace) && $namespace !== '' ? $namespace : 'shardev';
    }
}