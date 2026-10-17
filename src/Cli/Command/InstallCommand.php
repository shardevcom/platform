<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;
use Shardevcom\Cli\Workspace\Component;
use Shardevcom\Cli\Workspace\ComponentSelector;
use Shardevcom\Cli\Workspace\DriverRegistry;
use Shardevcom\Cli\Workspace\Workspace;

/**
 * `platform install`: installs dependencies for every selected component.
 *
 * One command covers the whole workspace; each component is handed to its
 * driver (composer / npm / flutter / gradlew), so adding a client type never
 * changes this command.
 */
final class InstallCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'install';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getSummary(): string
    {
        return 'Install dependencies for the workspace components';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create(
            arguments: ['component' => 'Component to install (defaults to all enabled)'],
            options: [
                'only' => [
                    'description' => 'Comma-separated components to install',
                    'value' => true,
                ],
            ]
        );
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('platform install');
            $printer->info('Usage: platform install [component] [--only=web,mobile]');
            $printer->writeln();
            $printer->note('Runs composer / npm install / flutter pub get / gradlew for the selected components.');
        });
    }

    public function execute(Input $input, Context $context): int
    {
        $workspace = $context->requireWorkspace();
        $targets = ComponentSelector::resolve($workspace, $input->argument('component'), $input->option('only'));
        $registry = new DriverRegistry($context);
        $printer = $context->printer();

        $printer->heading('Installing workspace dependencies');

        foreach ($targets as $component) {
            $this->install($registry, $component, $workspace, $printer);
        }

        $printer->success('Dependencies installed for '.count($targets).' component(s).');

        return 0;
    }

    private function install(DriverRegistry $registry, Component $component, Workspace $workspace, Printer $printer): void
    {
        $path = $workspace->componentPath($component->name);
        $printer->step(1, 1, "Installing {$component->name} ({$component->type->value})");
        $registry->driver($component->type)->install($component, $path);
    }
}
