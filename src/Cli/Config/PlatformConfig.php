<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Config;

use Shardevcom\Cli\Exception\CommandFailed;
use Shardevcom\Framework;

/**
 * Boundary: where a project keeps its packages and which vendor directory its
 * own packages live in.
 *
 * These two values are the only thing `platform package make`, `platform demo`
 * and `platform doctor` need to agree on, so they are stored once (in
 * config/platform.php) and read back by every later command instead of being
 * hardcoded per command.
 */
final class PlatformConfig
{
    public const DEFAULT_PACKAGES_DIR = Framework::DEFAULT_PACKAGES_DIR;

    /**
     * Same default the runtime uses (Framework), so a project generated with
     * no explicit config still discovers the packages that ship with it.
     */
    public const DEFAULT_VENDOR_NAMESPACE = Framework::DEFAULT_VENDOR_NAMESPACE;

    public readonly string $packagesDir;

    public readonly string $vendorNamespace;

    public function __construct(
        string $packagesDir = self::DEFAULT_PACKAGES_DIR,
        string $vendorNamespace = self::DEFAULT_VENDOR_NAMESPACE,
    ) {
        $this->packagesDir = self::assertPackagesDir($packagesDir);
        $this->vendorNamespace = self::assertVendor($vendorNamespace);
    }

    public static function defaults(): self
    {
        return new self();
    }

    public function withPackagesDir(string $packagesDir): self
    {
        return new self($packagesDir, $this->vendorNamespace);
    }

    public function withVendorNamespace(string $vendorNamespace): self
    {
        return new self($this->packagesDir, $vendorNamespace);
    }

    /**
     * Absolute path of the packages directory inside a project.
     */
    public function packagesPath(string $projectRoot): string
    {
        return rtrim($projectRoot, '/').'/'.$this->packagesDir;
    }

    /**
     * Absolute path where packages created from now on will be written.
     */
    public function packagePath(string $projectRoot, string $name): string
    {
        return $this->packagesPath($projectRoot).'/'.$this->vendorNamespace.'/'.$name;
    }

    /**
     * @return array{dir: string, vendor: string}
     */
    public function toArray(): array
    {
        return ['dir' => $this->packagesDir, 'vendor' => $this->vendorNamespace];
    }

    /**
     * The block written to config/platform.php.
     */
    public function toPhpBlock(string $indent = '    '): string
    {
        return $indent."'packages' => [\n"
            .$indent."    'dir' => '{$this->packagesDir}',\n"
            .$indent."    'vendor' => '{$this->vendorNamespace}',\n"
            .$indent.']';
    }

    /**
     * A project-relative path, so the CLI can never be pointed outside the
     * project it is configuring.
     */
    private static function assertPackagesDir(string $packagesDir): string
    {
        $trimmed = trim($packagesDir, "/ \t\n");

        if ($trimmed === '') {
            throw CommandFailed::create('The packages directory cannot be empty.');
        }

        if (str_starts_with($packagesDir, '/') || preg_match('#^[a-zA-Z]:#', $packagesDir) === 1) {
            throw CommandFailed::create("The packages directory must be relative to the project, got '{$packagesDir}'.");
        }

        foreach (explode('/', $trimmed) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..'
                || preg_match('#^[a-zA-Z0-9._-]+$#', $segment) !== 1) {
                throw CommandFailed::create(
                    "Invalid packages directory '{$packagesDir}'. Use relative segments like 'packages' or 'src/packages'."
                );
            }
        }

        return $trimmed;
    }

    private static function assertVendor(string $vendor): string
    {
        if (preg_match('#^[a-z][a-z0-9-]*$#', $vendor) !== 1) {
            throw CommandFailed::create(
                "Invalid vendor '{$vendor}'. Use lowercase letters, digits and dashes, starting with a letter."
            );
        }

        return $vendor;
    }
}