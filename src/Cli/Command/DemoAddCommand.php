<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;
use Shardevcom\Cli\Scaffold\ClientInstaller;
use Shardevcom\Cli\Scaffold\DemoInstaller;
use Shardevcom\Cli\Scaffold\MobileClientInstaller;

/**
 * `platform demo add <package>`: copies the demo into the project.
 *
 * With `--with-client` it also drops the reference SPA (demo/web, one Vite app
 * per guard) into ./client; with `--with-mobile-client` it drops the reference
 * Flutter app (demo/mobile, one flavor per guard) into ./mobile-client.
 * `--no-install` copies the client(s) without running npm install / flutter
 * pub get.
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
        return InputDefinition::create(
            arguments: [
                'package' => 'Demo package name (defaults to product)',
            ],
            options: [
                'with-client' => ['description' => 'Also copy the reference client (web monorepo) into ./client'],
                'with-mobile-client' => ['description' => 'Also copy the reference client (Flutter app) into ./mobile-client'],
                'no-install' => ['description' => 'Copy the client(s) but skip npm install / flutter pub get'],
            ],
        );
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('platform demo add');
            $printer->info('Usage: platform demo add [package] [--with-client] [--with-mobile-client] [--no-install]');
            $printer->writeln();
            $printer->note('The package is copied into the packages directory and vendor from config/platform.php.');
            $printer->note('--with-client copies ./client (Vite monorepo, one app per guard) and runs npm install unless --no-install.');
            $printer->note('--with-mobile-client copies ./mobile-client (Flutter app, one flavor per guard) and runs flutter pub get unless --no-install.');
        });
    }

    public function execute(Input $input, Context $context): int
    {
        $printer = $context->printer();
        $name = $input->argument('package') ?? 'product';
        $withClient = $input->flag('with-client');
        $withMobileClient = $input->flag('with-mobile-client');
        $installDependencies = ! $input->flag('no-install');
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

        if ($withClient) {
            $client = new ClientInstaller($context);
            $result = $client->install($projectRoot, $installDependencies);

            $printer->success('Client installed: '.$context->filesystem->relativePath($result['path'], $projectRoot));
            $printer->writeln();

            if ($result['dependencies']) {
                $printer->note('Client dependencies installed with npm.');
            } else {
                $printer->note('Run `npm install && npm run dev` inside the client directory.');
            }
        }

        if ($withMobileClient) {
            $mobile = new MobileClientInstaller($context);
            $result = $mobile->install($projectRoot, $installDependencies);

            $printer->success('Mobile client installed: '.$context->filesystem->relativePath($result['path'], $projectRoot));
            $printer->writeln();

            if ($result['dependencies']) {
                $printer->note('Mobile client dependencies installed with flutter pub get.');
            } else {
                $printer->note('Run `flutter pub get` inside the mobile-client directory.');
            }
        }

        $printer->note('Next: artisan serve, then check /api/v1/'.$name.'s and /graphql');

        return 0;
    }
}
