<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Scaffold;

use Shardevcom\Cli\Config\PlatformConfig;
use Shardevcom\Cli\Context;
use Shardevcom\Cli\Exception\CommandFailed;

/**
 * Boundary: installs a demo package shipped by the framework into a project.
 *
 * The demo lives in the framework (demo/packages/<vendor>/<name>) and is copied
 * into the project's configured packages directory and vendor, so the project
 * owns the code afterwards. Cross-package requirements are rewritten to the
 * names the project actually installed, otherwise `composer update` would ask
 * for packages that do not exist.
 */
final class DemoInstaller
{
    public function __construct(private readonly Context $context) {}

    /**
     * Demo packages available in the framework.
     *
     * @return list<string>
     */
    public function available(): array
    {
        $root = $this->context->frameworkPath('demo/packages');
        $names = [];

        foreach ($this->context->filesystem->directories($root) as $vendor) {
            foreach ($this->context->filesystem->directories($vendor) as $package) {
                if ($this->context->filesystem->isFile($package.'/composer.json')) {
                    $names[] = basename($package);
                }
            }
        }

        sort($names);

        return $names;
    }

    public function source(string $name): string
    {
        foreach ($this->vendors() as $vendor) {
            $candidate = $this->context->frameworkPath('demo/packages/'.$vendor.'/'.$name);

            if ($this->context->filesystem->isFile($candidate.'/composer.json')) {
                return $candidate;
            }
        }

        throw CommandFailed::create(
            "Demo package '{$name}' is not available. Installed in this framework: "
            .($this->available() === [] ? 'none' : implode(', ', $this->available()))
        );
    }

    /**
     * @return array{package: string, path: string, vendor: string, skipped: bool}
     */
    public function install(string $name, ?string $projectRoot = null, bool $allowSkip = false): array
    {
        $config = $this->context->config();
        $projectRoot ??= $this->context->projectRoot();
        $source = $this->source($name);
        $target = $config->packagePath($projectRoot, $name);

        if ($this->context->filesystem->exists($target)) {
            if ($allowSkip) {
                return ['package' => $config->vendorNamespace.'/'.$name, 'path' => $target, 'vendor' => $config->vendorNamespace, 'skipped' => true];
            }

            throw CommandFailed::create(
                "'{$config->vendorNamespace}/{$name}' is already installed at {$target}. Remove it first."
            );
        }

        $this->context->filesystem->copyDirectory($source, $target);
        $this->context->filesystem->createDirectory($target.'/database/migrations');
        $this->rewriteManifest($source.'/composer.json', $target.'/composer.json', $config, $name);

        $this->wire($projectRoot);

        return ['package' => $config->vendorNamespace.'/'.$name, 'path' => $target, 'vendor' => $config->vendorNamespace, 'skipped' => false];
    }

    private function wire(string $projectRoot): void
    {
        if (! $this->context->filesystem->isFile($projectRoot.'/composer.json')) {
            return;
        }

        $catalog = $this->context->projectCatalog();
        (new ComposerEditor($this->context->filesystem, $catalog))->configure($projectRoot);

        $this->context->process->run('composer update --no-interaction', $projectRoot);
        $this->context->process->run('composer dump-autoload', $projectRoot);
        $this->context->process->run('artisan migrate --force', $projectRoot);
    }

    /**
     * The demo is renamed to the project's vendor and its platform
     * requirements are mapped onto the packages this project installed.
     */
    private function rewriteManifest(
        string $sourceManifest,
        string $targetManifest,
        PlatformConfig $config,
        string $name,
    ): void {
        $manifest = $this->context->filesystem->readJson($targetManifest) ?? [];
        $manifest['name'] = $config->vendorNamespace.'/'.$name;
        $manifest['require'] = $this->remapRequirements(
            (array) ($manifest['require'] ?? []),
            $this->context->projectCatalog()
        );

        $this->context->filesystem->writeJson($targetManifest, $manifest);
    }

    /**
     * @param array<string, mixed> $requires
     *
     * @return array<string, mixed>
     */
    private function remapRequirements(array $requires, \Shardevcom\Cli\Packages\PackageCatalog $catalog): array
    {
        $remapped = [];

        foreach ($requires as $requirement => $constraint) {
            if (! is_string($requirement)) {
                continue;
            }

            $remapped[$this->requirementName($requirement, $catalog)] = $constraint;
        }

        ksort($remapped);

        return $remapped;
    }

    /**
     * Matches on the package part, so `shardev/core` resolves to whatever the
     * project really installed (`shardevcom/core`), and `php` is left alone.
     */
    private function requirementName(string $requirement, \Shardevcom\Cli\Packages\PackageCatalog $catalog): string
    {
        if (! str_contains($requirement, '/')) {
            return $requirement;
        }

        $wanted = substr($requirement, strpos($requirement, '/') + 1);

        foreach ($catalog->packages() as $package) {
            if ($package->package === $wanted) {
                return $package->name;
            }
        }

        return $requirement;
    }

    /**
     * @return list<string>
     */
    private function vendors(): array
    {
        $vendors = array_map(
            'basename',
            $this->context->filesystem->directories($this->context->frameworkPath('demo/packages'))
        );

        return $vendors;
    }
}