<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Scaffold;

use Shardevcom\Cli\Filesystem\Filesystem;
use Shardevcom\Cli\Packages\PackageCatalog;

/**
 * Boundary: registers the platform providers in a generated project.
 *
 * Laravel 12 keeps providers in bootstrap/providers.php; older/other layouts
 * are supported through bootstrap/app.php's withProviders(). Both are written
 * from the discovered package list.
 */
final class BootstrapEditor
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly PackageCatalog $catalog,
    ) {}

    /**
     * @return list<string> the providers that were registered
     */
    public function registerProviders(string $projectPath): array
    {
        $providers = $this->catalog->providersForBootstrap();

        if ($providers === []) {
            return [];
        }

        $providersFile = $projectPath.'/bootstrap/providers.php';

        if ($this->filesystem->isFile($providersFile)) {
            $this->writeProvidersFile($providersFile, $providers);

            return $providers;
        }

        $appFile = $projectPath.'/bootstrap/app.php';

        if ($this->filesystem->isFile($appFile)) {
            return $this->writeAppFile($appFile, $providers);
        }

        return [];
    }

    /**
     * @param list<string> $providers
     */
    private function writeProvidersFile(string $file, array $providers): void
    {
        $contents = $this->filesystem->read($file);

        foreach ($providers as $provider) {
            if (str_contains($contents, $provider)) {
                continue;
            }

            $contents = preg_replace(
                '/return \[/',
                "return [\n    \\{$provider}::class,",
                $contents,
                1
            ) ?? $contents;
        }

        $this->filesystem->write($file, $contents);
    }

    /**
     * @param list<string> $providers
     *
     * @return list<string>
     */
    private function writeAppFile(string $file, array $providers): array
    {
        $contents = $this->filesystem->read($file);

        $block = "->withProviders([\n";

        foreach ($providers as $provider) {
            $block .= "    \\{$provider}::class,\n";
        }

        $block .= "])\n\n";

        if (! str_contains($contents, '->create()')) {
            return [];
        }

        $contents = str_replace('->create()', $block.'->create()', $contents);
        $this->filesystem->write($file, $contents);

        return $providers;
    }
}