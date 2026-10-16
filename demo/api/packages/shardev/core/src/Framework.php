<?php

declare(strict_types=1);

namespace Shardevcom\Core;

/**
 * Framework identity and compatibility matrix.
 *
 * Boundary: single source of truth for which Laravel/PHP versions a
 * Framework release supports. The `platform new` installer (T-003) and
 * `platform doctor` (T-005) both validate against this matrix, so an
 * unsupported Laravel version can never be scaffolded by mistake.
 */
final class Framework
{
    public const VERSION = '0.1.0-dev';

    /**
     * Framework 1.x line. Supports Laravel 12.x and 13.x on PHP ^8.3.
     *
     * @var array<string, array{laravel: string, php: string}>
     */
    public const COMPATIBILITY = [
        '1.x' => ['laravel' => '^12.0|^13.0', 'php' => '^8.3'],
    ];

    /**
     * Default packages directory name (relative to project root).
     */
    public const DEFAULT_PACKAGES_DIR = 'packages';

    /**
     * Default vendor namespace prefix for packages.
     */
    public const DEFAULT_VENDOR_NAMESPACE = 'shardev';

    /**
     * Get the packages directory path for a given project root.
     */
    public static function packagesPath(string $projectRoot): string
    {
        $packagesDir = env('PLATFORM_PACKAGES_DIR', self::DEFAULT_PACKAGES_DIR);
        return rtrim($projectRoot, '/') . '/' . ltrim($packagesDir, '/');
    }

    /**
     * Get the vendor namespace prefix for packages.
     */
    public static function vendorNamespace(): string
    {
        return env('PLATFORM_VENDOR_NAMESPACE', self::DEFAULT_VENDOR_NAMESPACE);
    }

    public static function version(): string
    {
        return self::VERSION;
    }

    /**
     * Accepts '12', '12.61', '^12.0', '12.x', '13', '13.0', '^13.0', '13.x' style input.
     */
    public static function supportsLaravel(string $requested): bool
    {
        $normalized = strtolower(trim($requested));

        foreach (['^12.0', '12', '^13.0', '13'] as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                return true;
            }
        }

        if (preg_match('/^(?:12|13)(?:\.\d+)?(?:\.x)?$/', $normalized) === 1) {
            return true;
        }

        return false;
    }

    public static function incompatibilityMessage(string $requested): string
    {
        $supported = implode(', ', array_map(
            static fn (string $line, array $m): string => "Framework {$line} -> Laravel {$m['laravel']} + PHP {$m['php']}",
            array_keys(self::COMPATIBILITY),
            array_values(self::COMPATIBILITY)
        ));

        return "Laravel version [{$requested}] is not supported. Supported: {$supported}.";
    }
}
