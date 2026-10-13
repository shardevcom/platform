<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;
use Shardevcom\Cli\Scaffold\DemoInstaller;

/**
 * `platform demo add <package>`: copies the demo into the project.
 */
final class DemoAddCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'demo add';
    }

    public function getAliases(): array
    {
        return ['demo:add'];
    }

    public function getSummary(): string
    {
        return 'Install a demo package into this project';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create(arguments: [
            'package' => 'Demo package name (defaults to product)',
        ]);
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('platform demo add');
            $printer->info('Usage: platform demo add [package]');
            $printer->writeln();
            $printer->note('The package is copied into the packages directory and vendor from config/platform.php.');
        });
    }

    public function execute(Input $input, Context $context): int
    {
        $printer = $context->printer();
        $name = $input->argument('package') ?? 'product';
        $installer = new DemoInstaller($context);
        $config = $context->config();
        $projectRoot = $context->projectRoot();

        $printer->heading("Installing demo '{$name}'");
        $printer->writeln();
        $printer->step(1, 4, 'Copying package files');
        $installed = $installer->install($name);
        $printer->step(2, 4, 'Rewriting manifest for '.$config->vendorNamespace);
        $printer->step(3, 4, 'Wiring composer.json');
        $printer->step(4, 4, 'Running migrations');
        $printer->writeln();

        $printer->success('Demo installed: '.$installed['package']);
        $printer->writeln();
        $printer->table(['Where', 'Value'], [
            ['Path', $context->filesystem->relativePath($installed['path'], $projectRoot)],
            ['Vendor', $installed['vendor']],
            ['Packages dir', $config->packagesDir],
        ]);
        $printer->writeln();
        $printer->note('Next: artisan serve, then check /api/v1/'.$name.'s and /graphql');

        return 0;
    }
}