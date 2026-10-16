<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;
use Shardevcom\Cli\Plugins\PluginCatalog;

/**
 * `platform plugin list`: every plugin shipped by the installed packages.
 *
 * A plugin is an API package with a `plugin.json` manifest plus a web module
 * exposing one view per guard. Listing them from the manifests keeps the CLI,
 * the API and the SPA agreeing on the same set without a second registry.
 */
final class PluginListCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'plugin list';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getSummary(): string
    {
        return 'List the plugins installed in this project';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create();
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('platform plugin list');
            $printer->info('Usage: platform plugin list');
        });
    }

    public function execute(Input $input, Context $context): int
    {
        $printer = $context->printer();
        $plugins = (new PluginCatalog($context->filesystem, $context->packageCatalog()))->all();

        $printer->heading('Platform plugins');

        if ($plugins === []) {
            $printer->warning('No plugin manifests found. A plugin is a package that ships a plugin.json.');

            return 0;
        }

        $rows = [];

        foreach ($plugins as $plugin) {
            $rows[] = [
                $plugin->name,
                $plugin->resource,
                $plugin->guardSummary(),
                (string) count($plugin->actions),
            ];
        }

        $printer->writeln();
        $printer->table(['Plugin', 'Resource', 'Guards', 'Actions'], $rows);
        $printer->writeln();
        $printer->note('Each plugin ships an API package (plugin.json) plus a web module with one view per guard.');

        return 0;
    }
}
