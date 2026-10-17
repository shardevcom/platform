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
 * `platform build`: builds every selected component.
 */
final class BuildCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'build';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getSummary(): string
    {
        return 'Build the workspace components';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create(
            arguments: ['component' => 'Component to build (defaults to all enabled)'],
            options: [
                'only' => [
                    'description' => 'Comma-separated components to build',
                    'value' => true,
                ],
            ]
        );
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('platform build');
            $printer->info('Usage: platform build [component] [--only=web,desktop]');
            $printer->writeln();
            $printer->note('Runs composer dump-autoload / npm run build / flutter build / gradlew build.');
        });
    }

    public function execute(Input $input, Context $context): int
    {
        $workspace = $context->requireWorkspace();
        $targets = ComponentSelector::resolve($workspace, $input->argument('component'), $input->option('only'));
        $registry = new DriverRegistry($context);
        $printer = $context->printer();

        $printer->heading('Building workspace');

        foreach ($targets as $component) {
            $this->build($registry, $component, $workspace, $printer);
        }

        $printer->success('Built '.count($targets).' component(s).');

        return 0;
    }

    private function build(DriverRegistry $registry, Component $component, Workspace $workspace, Printer $printer): void
    {
        $path = $workspace->componentPath($component->name);
        $printer->step(1, 1, "Building {$component->name} ({$component->type->value})");
        $registry->driver($component->type)->build($component, $path);
    }
}
