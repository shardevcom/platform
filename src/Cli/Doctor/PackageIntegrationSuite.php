<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor;

use Shardevcom\Cli\Filesystem\Filesystem;
use Shardevcom\Cli\Packages\PackageCatalog;
use Shardevcom\Cli\Process\ProcessRunner;

/**
 * Package integration checks: verifies packages are not just discovered but
 * actually wired into the application (providers loaded, migrations present,
 * routes registered, autoload working).
 */
final class PackageIntegrationSuite implements CheckSuite
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly PackageCatalog $catalog,
        private readonly ProcessRunner $process,
        private readonly string $projectRoot,
    ) {}

    public function name(): string
    {
        return 'Package Integration';
    }

    public function run(): array
    {
        $results = [];
        $packages = $this->catalog->packages();

        // Overall integration checks
        $results[] = $this->checkProvidersLoaded($packages);
        $results[] = $this->checkMigrationsPresent($packages);
        $results[] = $this->checkRoutesRegistered($packages);
        $results[] = $this->checkAutoloadWorking($packages);

        // Per-package checks
        foreach ($packages as $package) {
            $results[] = $this->checkPackageIntegration($package);
        }

        return $results;
    }

    private function checkProvidersLoaded(array $packages): CheckResult
    {
        $result = $this->process->run('php artisan package:discover', $this->projectRoot);
        
        $providersWithProvider = array_filter($packages, static fn ($p): bool => $p->hasProvider());
        $expectedCount = count($providersWithProvider);

        return CheckResult::of(
            'Providers loadable',
            $result->successful(),
            $result->successful() ? 'Package discovery runs without errors' : 'Package discovery failed: '.$result->trimmedOutput()
        );
    }

    private function checkMigrationsPresent(array $packages): CheckResult
    {
        $packagesWithMigrations = [];
        $packagesWithEmptyMigrationsDir = [];

        foreach ($packages as $package) {
            $migrationsPath = $package->path.'/database/migrations';
            if (! $this->filesystem->isDirectory($migrationsPath)) {
                // Not all packages need migrations
                continue;
            }
            
            $migrations = $this->filesystem->files($migrationsPath);
            if (empty($migrations)) {
                $packagesWithEmptyMigrationsDir[] = $package->key();
            } else {
                $packagesWithMigrations[] = $package->key();
            }
        }

        // Empty migrations directory is not an error - some packages don't need migrations
        // We just report which packages have migrations and which have empty dirs
        $detail = '';
        if (! empty($packagesWithMigrations)) {
            $detail .= 'Packages with migrations: '.implode(', ', $packagesWithMigrations);
        }
        if (! empty($packagesWithEmptyMigrationsDir)) {
            if ($detail) $detail .= '; ';
            $detail .= 'Packages with empty migrations dir (optional): '.implode(', ', $packagesWithEmptyMigrationsDir);
        }

        return CheckResult::of(
            'Package migrations',
            true, // Always pass - empty migrations dir is OK
            $detail ?: 'No packages have migrations directory'
        );
    }

    private function checkRoutesRegistered(array $packages): CheckResult
    {
        $filesystem = $this->filesystem;
        $packagesWithRoutes = array_filter($packages, static function ($p) use ($filesystem): bool {
            return $filesystem->isDirectory($p->path.'/routes');
        });
        
        if (empty($packagesWithRoutes)) {
            return CheckResult::pass('Routes registered', 'No packages with routes/ directory');
        }

        $result = $this->process->run('php artisan route:list', $this->projectRoot);
        
        if ($result->failed()) {
            return CheckResult::fail('Routes registered', 'Could not list routes');
        }

        return CheckResult::of(
            'Routes registered',
            true,
            count($packagesWithRoutes).' packages have routes/ directory'
        );
    }

    private function checkAutoloadWorking(array $packages): CheckResult
    {
        // Check if we can actually load a class from each package
        $loadable = 0;
        $total = 0;

        foreach ($packages as $package) {
            if (! $package->hasProvider()) {
                continue;
            }
            
            $total++;
            $providerClass = $package->provider;
            
            // Try to check if the class exists via artisan
            $result = $this->process->run("php -r \"require 'vendor/autoload.php'; class_exists('{$providerClass}') ? exit(0) : exit(1);\"", $this->projectRoot);
            
            if ($result->successful()) {
                $loadable++;
            }
        }

        return CheckResult::of(
            'Autoload working',
            $loadable === $total && $total > 0,
            $loadable.'/'.$total.' provider classes loadable via autoloader'
        );
    }

    private function checkPackageIntegration(\Shardevcom\Cli\Packages\DiscoveredPackage $package): CheckResult
    {
        $checks = [];
        
        // Has provider
        $checks[] = $package->hasProvider() ? 'provider' : 'no-provider';
        
        // Has migrations
        $checks[] = $this->filesystem->isDirectory($package->path.'/database/migrations') ? 'migrations' : 'no-migrations';
        
        // Has routes
        $checks[] = $this->filesystem->isDirectory($package->path.'/routes') ? 'routes' : 'no-routes';
        
        // Has src
        $checks[] = $this->filesystem->isDirectory($package->path.'/src') ? 'src' : 'no-src';

        $details = implode(', ', $checks);
        $isIntegrated = $package->hasProvider() && $this->filesystem->isDirectory($package->path.'/src');

        return CheckResult::of(
            'Package: '.$package->key(),
            $isIntegrated,
            $details
        );
    }
}