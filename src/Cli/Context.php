<?php

declare(strict_types=1);

namespace Shardevcom\Cli;

use Shardevcom\Cli\Config\PlatformConfig;
use Shardevcom\Cli\Config\PlatformConfigFile;
use Shardevcom\Cli\Filesystem\Filesystem;
use Shardevcom\Cli\Filesystem\LocalFilesystem;
use Shardevcom\Cli\Packages\PackageCatalog;
use Shardevcom\Cli\Process\ProcessRunner;
use Shardevcom\Cli\Process\ShellProcessRunner;
use Shardevcom\Cli\Console\OutputInterface;
use Shardevcom\Cli\Console\Printer;

/**
 * Boundary: the capabilities a command is allowed to use.
 *
 * Everything mutable or external (terminal, filesystem, processes, discovered
 * packages, working directory) is injected here instead of reached through
 * globals, which is the whole reason the CLI is testable.
 */
final class Context
{
    public function __construct(
        public readonly OutputInterface $output,
        public readonly Filesystem $filesystem,
        public readonly ProcessRunner $process,
        public readonly string $workingDirectory,
        public readonly string $frameworkRoot,
        public readonly ?PackageCatalog $catalog = null,
        public readonly ?PlatformConfig $config = null,
    ) {}

    private ?PlatformConfig $resolvedConfig = null;

    /**
     * Builds the production context: real terminal, real filesystem, real
     * shell, and a catalog rooted at the framework that ships these packages.
     */
    public static function create(
        string $frameworkRoot,
        ?OutputInterface $output = null,
        ?Filesystem $filesystem = null,
        ?ProcessRunner $process = null,
        ?string $workingDirectory = null,
    ): self {
        $root = rtrim($frameworkRoot, '/');
        $cwd = $workingDirectory ?? (getcwd() ?: $root);

        return new self(
            output: $output ?? Printer::detect(),
            filesystem: $filesystem ?? new LocalFilesystem(),
            process: $process ?? new ShellProcessRunner(),
            workingDirectory: $cwd,
            frameworkRoot: $root,
            catalog: new PackageCatalog($filesystem ?? new LocalFilesystem(), $root),
        );
    }

    public function printer(): Printer
    {
        if ($this->output instanceof Printer) {
            return $this->output;
        }

        throw new \LogicException('Context output must be a Printer to use printer().');
    }

    /**
     * Configuration of the project in the working directory, read once from
     * config/platform.php. Commands must never assume the defaults.
     */
    public function config(): PlatformConfig
    {
        return $this->resolvedConfig ??= $this->config
            ?? (new PlatformConfigFile($this->filesystem, $this->projectRoot()))->load();
    }

    /**
     * Catalog rooted at the project being configured (not at the framework),
     * honouring the configured packages directory.
     */
    public function projectCatalog(?PlatformConfig $config = null): PackageCatalog
    {
        $config ??= $this->config();

        return new PackageCatalog(
            $this->filesystem,
            $this->projectRoot(),
            $config->packagesDir,
            $config->vendorNamespace,
        );
    }

    public function configFile(): PlatformConfigFile
    {
        return new PlatformConfigFile($this->filesystem, $this->projectRoot());
    }

    /**
     * The catalog describing the packages the current directory should see.
     *
     * Inside an installed project that is the project's own packages (honouring
     * the configured directory); anywhere else it is the framework's.
     */
    public function packageCatalog(): PackageCatalog
    {
        if ($this->isInstalledProject()) {
            return $this->projectCatalog();
        }

        return $this->catalog ?? throw new \LogicException('Context was built without a package catalog.');
    }

    /**
     * True when the working directory sits inside a generated Laravel project
     * rather than in the framework itself.
     */
    public function isInstalledProject(): bool
    {
        $root = $this->projectRoot();

        return $root !== rtrim($this->frameworkRoot, '/')
            && isset($this->filesystem->readJson($root.'/composer.json')['require']['laravel/framework']);
    }

    public function path(string $relative): string
    {
        return rtrim($this->workingDirectory, '/').'/'.ltrim($relative, '/');
    }

    public function frameworkPath(string $relative): string
    {
        return $this->frameworkRoot.'/'.ltrim($relative, '/');
    }

    /**
     * Nearest ancestor directory (starting at the working directory) that has a
     * composer.json requiring laravel/framework. Falls back to the cwd.
     */
    public function projectRoot(): string
    {
        $directory = $this->workingDirectory;

        while ($directory !== '' && $directory !== '/' && $directory !== '.') {
            $composer = $this->filesystem->readJson($directory.'/composer.json');

            if (isset($composer['require']['laravel/framework'])) {
                return $directory;
            }

            $directory = dirname($directory);
        }

        return $this->workingDirectory;
    }
}