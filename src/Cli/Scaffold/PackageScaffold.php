<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Scaffold;

use Shardevcom\Cli\Config\PlatformConfig;
use Shardevcom\Cli\Exception\CommandFailed;
use Shardevcom\Cli\Filesystem\Filesystem;

/**
 * Boundary: the single description of what a platform package looks like.
 *
 * `plan()` returns the tree and file contents without touching disk, so
 * `platform package make --dry-run` and the real run can never diverge, and a
 * test can assert the whole structure in memory.
 */
final class PackageScaffold
{
    private const STRUCTURE = [
        'src',
        'config',
        'routes',
        'database/migrations',
        'tests',
    ];

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $projectRoot,
        private readonly PlatformConfig $config = new PlatformConfig(),
    ) {}

    /**
     * @return array{root: string, namespace: string, provider: string, directories: list<string>, files: array<string, string>}
     */
    public function plan(string $name, ?string $vendor = null): array
    {
        $effective = $vendor === null ? $this->config : $this->config->withVendorNamespace($vendor);
        $vendor = $effective->vendorNamespace;
        $this->assertValidName($name);

        $namespace = $this->studly($vendor).'\\'.$this->studly($name);
        $providerClass = $this->studly($name).'ServiceProvider';
        $root = $effective->packagePath($this->projectRoot, $name);

        if ($this->filesystem->isDirectory($root)) {
            throw CommandFailed::create("Package '{$vendor}/{$name}' already exists at {$root}");
        }

        $directories = array_map(
            static fn (string $directory): string => $root.'/'.$directory,
            self::STRUCTURE
        );

        $files = [
            $root.'/composer.json' => $this->composerJson($vendor, $name, $namespace, $namespace.'\\'.$providerClass),
            $root.'/src/'.$providerClass.'.php' => $this->serviceProvider($namespace, $providerClass, $name),
            $root.'/config/'.$name.'.php' => $this->configFile($name),
            $root.'/routes/api.php' => $this->routesFile(),
            $root.'/database/migrations/.gitkeep' => '',
            $root.'/tests/.gitkeep' => '',
        ];

        return [
            'root' => $root,
            'namespace' => $namespace,
            'provider' => $namespace.'\\'.$providerClass,
            'directories' => $directories,
            'files' => $files,
        ];
    }

    /**
     * @param array{root: string, namespace: string, provider: string, directories: list<string>, files: array<string, string>} $plan
     */
    public function create(array $plan): void
    {
        foreach ($plan['directories'] as $directory) {
            $this->filesystem->createDirectory($directory);
        }

        foreach ($plan['files'] as $path => $contents) {
            $this->filesystem->write($path, $contents);
        }
    }

    private function assertValidName(string $name): void
    {
        if (preg_match('/^[a-z][a-z0-9-]*$/', $name) !== 1) {
            throw CommandFailed::create(
                "Invalid package name '{$name}'. Use lowercase letters, digits and dashes, starting with a letter."
            );
        }
    }

    private function composerJson(string $vendor, string $name, string $namespace, string $provider): string
    {
        $manifest = [
            'name' => $vendor.'/'.$name,
            'description' => "Shardev platform {$name} package",
            'type' => 'platform-package',
            'license' => 'MIT',
            'require' => ['php' => '^8.2'],
            'autoload' => ['psr-4' => [$namespace.'\\' => 'src/']],
            'extra' => ['platform' => ['provider' => $provider]],
        ];

        return json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }

    private function serviceProvider(string $namespace, string $providerClass, string $name): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        use Illuminate\\Support\\ServiceProvider;

        /**
         * Integration point for the {$name} package.
         *
         * Boundary: wiring only. Business logic belongs in Application use
         * cases, never in a service provider.
         */
        final class {$providerClass} extends ServiceProvider
        {
            public function register(): void
            {
                //
            }

            public function boot(): void
            {
                //
            }
        }

        PHP;
    }

    private function configFile(string $name): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        return [
            /*
            |--------------------------------------------------------------------------
            | {$name} Configuration
            |--------------------------------------------------------------------------
            */
        ];

        PHP;
    }

    private function routesFile(): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        use Illuminate\\Support\\Facades\\Route;

        /*
        |--------------------------------------------------------------------------
        | Package REST Routes
        |--------------------------------------------------------------------------
        |
        | REST and GraphQL are thin adapters over the same use cases; register
        | them here once the package has controllers.
        |
        */

        PHP;
    }

    private function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $value)));
    }
}