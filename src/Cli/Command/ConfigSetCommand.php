<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Config\PlatformConfig;
use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Exception\CommandFailed;
use Shardevcom\Cli\Exception\InvalidInput;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;
use Shardevcom\Cli\Scaffold\ComposerEditor;
use Shardevcom\Cli\Scaffold\EnvEditor;

/**
 * `platform config packages <dir>` and `platform config vendor <vendor>`.
 *
 * Changing the packages directory also moves the packages that are already
 * installed and rewires composer.json, so nothing keeps pointing at the old
 * location. Both values land in config/platform.php (source of truth for the
 * CLI) and in .env (source of truth for the running application).
 */
final class ConfigSetCommand implements CommandInterface
{
    public function __construct(private readonly string $setting) {}

    public function getName(): string
    {
        return 'config '.$this->setting;
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getSummary(): string
    {
        return $this->isPackagesSetting()
            ? 'Change the directory where packages are installed'
            : 'Change the vendor directory new packages are created in';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create(arguments: [
            'value' => $this->isPackagesSetting()
                ? 'New packages directory, relative to the project (e.g. src/platform)'
                : 'New vendor directory (e.g. acme)',
        ]);
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('platform '.$this->getName());
            $printer->info('Usage: platform '.$this->getName().' <value>');
            $printer->writeln();
            $printer->note($this->getSummary().'. Stored in config/platform.php.');
        });
    }

    public function execute(Input $input, Context $context): int
    {
        $printer = $context->printer();
        $value = $input->argument('value');

        if ($value === null) {
            throw InvalidInput::missingArgument('value', $this->getDefinition());
        }

        $current = $context->config();
        $projectRoot = $context->projectRoot();
        $updated = $this->isPackagesSetting()
            ? $current->withPackagesDir($value)
            : $current->withVendorNamespace($value);

        if ($updated->toArray() === $current->toArray()) {
            $printer->info('Already configured: '.$this->setting.' = '.$value);

            return 0;
        }

        $moved = $this->isPackagesSetting()
            ? $this->movePackages($context, $current, $updated, $projectRoot)
            : null;

        $context->configFile()->save($updated);
        $this->exportToEnv($context, $updated, $projectRoot);

        if ($context->filesystem->isFile($projectRoot.'/composer.json')) {
            (new ComposerEditor($context->filesystem, $context->projectCatalog($updated)))
                ->configure($projectRoot);

            $context->process->run('composer dump-autoload', $projectRoot);
        }

        // Re-publish platform configs if packages directory changed
        if ($this->isPackagesSetting() && $moved !== null) {
            $this->publishPlatformConfigs($context, $projectRoot);
        }

        $printer->success('Configuration updated');
        $printer->writeln();

        if ($moved !== null) {
            $printer->info('Packages moved: '.$moved);
        }

        $printer->table(['Setting', 'Value'], [
            ['Packages dir', $updated->packagesDir],
            ['Vendor', $updated->vendorNamespace],
            ['Config file', $context->configFile()->path()],
        ]);

        if (! $this->isPackagesSetting()) {
            $printer->writeln();
            $printer->note('Existing packages keep their directory; new ones are created in the new vendor.');
        }

        return 0;
    }

    private function publishPlatformConfigs(Context $context, string $projectRoot): void
    {
        $printer = $context->printer();
        $printer->step(1, 2, 'Publishing platform configs...');
        $context->process->run('php artisan vendor:publish --tag=platform-config --force', $projectRoot);
        $printer->step(2, 2, 'Publishing Passport configs...');
        $context->process->run('php artisan vendor:publish --tag=passport-config --force', $projectRoot);
        $printer->note('Platform configs re-published.');
    }

    private function isPackagesSetting(): bool
    {
        return $this->setting === 'packages';
    }

    private function movePackages(
        Context $context,
        PlatformConfig $from,
        PlatformConfig $to,
        string $projectRoot,
    ): ?string {
        $source = $from->packagesPath($projectRoot);
        $target = $to->packagesPath($projectRoot);

        if ($source === $target || ! $context->filesystem->isDirectory($source)) {
            return null;
        }

        if ($context->filesystem->exists($target)) {
            throw CommandFailed::create(
                "Cannot move packages to {$target}: it already exists. Merge it manually and run the command again."
            );
        }

        $context->filesystem->moveDirectory($source, $target);

        return $context->filesystem->relativePath($source, $projectRoot)
            .' -> '.$context->filesystem->relativePath($target, $projectRoot);
    }

    private function exportToEnv(Context $context, PlatformConfig $config, string $projectRoot): void
    {
        $env = $projectRoot.'/.env';

        if (! $context->filesystem->isFile($env)) {
            return;
        }

        (new EnvEditor($context->filesystem))->write($env, [
            'PLATFORM_PACKAGES_DIR' => $config->packagesDir,
            'PLATFORM_VENDOR_NAMESPACE' => $config->vendorNamespace,
        ], overwrite: false);
    }
}