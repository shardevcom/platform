<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor;

use Shardevcom\Cli\Filesystem\Filesystem;
use Shardevcom\Cli\Packages\PackageCatalog;

/**
 * Package checks: discovery, provider declaration, bootstrapper and autoload
 * wiring in the inspected project's composer.json.
 *
 * The expectation comes from the catalog (what the framework ships) instead of
 * a hardcoded minimum, so adding a package can never make doctor lie.
 */
final class PackagesSuite implements CheckSuite
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly PackageCatalog $catalog,
        private readonly string $projectRoot,
    ) {}

    public function name(): string
    {
        return 'Packages';
    }

    public function run(): array
    {
        $packages = $this->catalog->packages();
        $missingProviders = array_values(array_filter(
            $packages,
            static fn ($package): bool => ! $package->hasProvider()
        ));
        $bootstrapper = $this->catalog->platformBootstrapper();

        return [
            CheckResult::of(
                'Platform packages',
                $packages !== [],
                count($packages).' discovered in '.$this->catalog->packagesPath()
            ),
            CheckResult::of(
                'Package providers',
                $missingProviders === [],
                $missingProviders === []
                    ? count($packages).' declared'
                    : 'Missing provider: '.implode(', ', array_map(
                        static fn ($package): string => $package->key(),
                        $missingProviders
                    ))
            ),
            CheckResult::of(
                'Platform bootstrapper',
                $bootstrapper !== null,
                $bootstrapper ?? 'No PlatformServiceProvider found: discovery will not run'
            ),
            $this->autoloadIsWired(),
        ];
    }

    private function autoloadIsWired(): CheckResult
    {
        $composer = $this->filesystem->readJson($this->projectRoot.'/composer.json') ?? [];
        $psr4 = (array) ($composer['autoload']['psr-4'] ?? []);
        $expected = $this->catalog->psr4Map();
        $unwired = array_keys(array_diff_key($expected, $psr4));

        return CheckResult::of(
            'Package autoload',
            $unwired === [],
            $unwired === []
                ? count($expected).' namespaces mapped'
                : 'Unmapped: '.implode(', ', $unwired)
        );
    }
}