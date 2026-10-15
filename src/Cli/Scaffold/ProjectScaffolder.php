<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Scaffold;

use Shardevcom\Cli\Config\PlatformConfig;
use Shardevcom\Cli\Context;
use Shardevcom\Cli\Exception\CommandFailed;
use Shardevcom\Cli\Packages\PackageCatalog;
use Shardevcom\Cli\Process\ShellProcessRunner;

/**
 * Boundary: the `platform new` workflow, expressed as ordered steps over the
 * injected filesystem and process runner.
 *
 * Every step returns a ProcessResult-free boolean or throws CommandFailed, so
 * the whole flow is assertable with fakes and no real Laravel install.
 */
final class ProjectScaffolder
{
    public function __construct(private readonly Context $context)
    {
    }

    /**
     * Copies every discovered package into the new project.
     *
     * @return list<string> created package directories
     */
    public function installPackages(string $projectPath): array
    {
        $catalog = $this->catalog();
        $created = [];

        foreach ($catalog->packages() as $package) {
            $target = $projectPath.'/'.$catalog->targetPathFor($package);
            $this->context->filesystem->copyDirectory($package->path, $target);
            $created[] = $catalog->targetPathFor($package);
        }

        return $created;
    }

    public function configureComposer(string $projectPath): void
    {
        (new ComposerEditor($this->context->filesystem, $this->catalog()))->configure($projectPath);
    }

    /**
     * @return list<string>
     */
    public function registerProviders(string $projectPath): array
    {
        return (new BootstrapEditor($this->context->filesystem, $this->catalog()))->registerProviders($projectPath);
    }

    public function writePlatformConfig(string $projectPath, ?PlatformConfig $config = null): void
    {
        $file = $projectPath.'/config/platform.php';
        $config ??= $this->installedConfig();

        if ($this->context->filesystem->isFile($file)) {
            return;
        }

        $this->context->filesystem->write($file, <<<PHP
        <?php

        declare(strict_types=1);

        return [
            'version' => env('PLATFORM_VERSION', '0.1.0-dev'),
            'api' => [
                'rest' => env('API_REST_ENABLED', true),
                'graphql' => env('API_GRAPHQL_ENABLED', true),
            ],
        {$config->toPhpBlock()}
        ];

        PHP);
    }

    /**
     * Prepares .env for sqlite and creates the database file.
     */
    public function prepareEnvironment(string $projectPath, string $version, ?PlatformConfig $config = null): void
    {
        $example = $projectPath.'/.env.example';
        $env = $projectPath.'/.env';

        if ($this->context->filesystem->isFile($example)) {
            $this->context->filesystem->write($env, $this->context->filesystem->read($example));
        }

        $config ??= $this->installedConfig();

        (new EnvEditor($this->context->filesystem))->write($env, [
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => 'database/database.sqlite',
            'PLATFORM_VERSION' => $version,
            'API_REST_ENABLED' => 'true',
            'API_GRAPHQL_ENABLED' => 'true',
            'PLATFORM_PACKAGES_DIR' => $config->packagesDir,
            'PLATFORM_VENDOR_NAMESPACE' => $config->vendorNamespace,
        ]);

        $this->context->filesystem->touch($projectPath.'/database/database.sqlite');
    }

    public function installDependencies(string $projectPath): void
    {
        $this->mustRun('composer update --no-interaction -n', $projectPath, 'Composer could not install the project dependencies.');
    }

    public function publishPassportMigrations(string $projectPath): void
    {
        $this->mustRun('php artisan vendor:publish --tag=passport-migrations --force', $projectPath, 'Could not publish Passport migrations.');
    }

    public function generateKey(string $projectPath): void
    {
        $this->mustRun('php artisan key:generate --ansi', $projectPath, 'Could not generate the application key.');
    }

    public function migrate(string $projectPath): void
    {
        // Run Passport migrations first (they have older timestamps but were republished with new ones)
        $this->run('php artisan migrate --path=database/migrations/2026_10_08_*create_oauth* --force', $projectPath);
        $this->mustRun('php artisan migrate --force', $projectPath, 'Migrations failed.');
    }

    public function generatePassportKeys(string $projectPath): void
    {
        $this->mustRun('php artisan passport:keys --force', $projectPath, 'Could not generate Passport keys.');
    }

    public function createPersonalAccessClients(string $projectPath): void
    {
        // Create personal access clients for each platform after Passport is set up
        $this->mustRun('php artisan tinker --execute="
            \$repository = app(Laravel\Passport\ClientRepository::class);
            \$platforms = config(\"platforms.platforms\", []);
            foreach (\$platforms as \$platform) {
                \$repository->createPersonalAccessGrantClient(
                    \"Shardev \".\$platform[\"key\"].\" personal access\",
                    \$platform[\"provider\"],
                );
            }
        "', $projectPath, 'Could not create personal access clients.');
    }

    public function seed(string $projectPath): void
    {
        $this->run('php artisan db:seed --force', $projectPath);
    }

    public function dumpAutoload(string $projectPath): void
    {
        $this->mustRun('composer dump-autoload -o', $projectPath, 'Could not regenerate the autoloader.');
    }

    /**
     * Verifies the installed project boots and exposes the platform surface.
     *
     * @param list<array{0: string, 1: string, 2: string}> $expectations
     */
    public function verify(string $projectPath, array $expectations): void
    {
        foreach ($expectations as $expectation) {
            /** @var list{string, string, string} $expectation */
            [$command, $needle, $message] = $expectation;

            $result = $this->run($command, $projectPath);

            if ($result->failed() || ! $result->contains($needle)) {
                throw CommandFailed::create($message."\n".$result->trimmedOutput());
            }
        }
    }

    public function createLaravelProject(string $targetPath, string $laravelConstraint, bool $noInteraction): void
    {
        $flags = $noInteraction ? ' --no-interaction' : '';
        $name = basename($targetPath);
        $parent = dirname($targetPath);

        $result = $this->context->process->run(
            sprintf(
                'composer create-project laravel/laravel %s %s%s --prefer-dist',
                ShellProcessRunner::quote($name),
                ShellProcessRunner::quote($laravelConstraint),
                $flags
            ),
            $parent
        );

        if ($result->failed() || ! $this->context->filesystem->isDirectory($targetPath)) {
            throw CommandFailed::create(
                "Could not create the Laravel project in {$targetPath}\n".$result->trimmedOutput()
            );
        }
    }

    public function assertTargetIsFree(string $targetPath): void
    {
        if ($this->context->filesystem->isDirectory($targetPath)) {
            throw CommandFailed::create("Directory '{$targetPath}' already exists.");
        }
    }

    private function mustRun(string $command, string $workingDirectory, string $message): void
    {
        $result = $this->run($command, $workingDirectory);

        if ($result->failed()) {
            throw CommandFailed::create($message."\n".$result->trimmedOutput());
        }
    }

    private function run(string $command, string $workingDirectory): \Shardevcom\Cli\Process\ProcessResult
    {
        return $this->context->process->run($command, $workingDirectory);
    }

    private function catalog(): PackageCatalog
    {
        return $this->context->packageCatalog();
    }

    /**
     * Config describing where the packages were actually installed.
     *
     * A project must be told the truth: writing a default that disagrees with
     * the layout just created would make every later `platform package make`
     * and `platform doctor` look in a directory that does not exist.
     */
    private function installedConfig(): PlatformConfig
    {
        $catalog = $this->catalog();

        return new PlatformConfig($catalog->packagesDir(), $catalog->vendorNamespace());
    }
}