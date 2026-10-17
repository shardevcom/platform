<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Exception\InvalidInput;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;

/**
 * `platform remove <component>`: drops a component from the workspace.
 *
 * Removes the entry from platform.json; `--delete` also removes the directory.
 */
final class RemoveCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'remove';
    }

    public function getAliases(): array
    {
        return ['rm'];
    }

    public function getSummary(): string
    {
        return 'Remove a component from the workspace';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create(
            arguments: ['component' => 'Component to remove'],
            options: [
                'delete' => [
                    'description' => 'Also delete the component directory',
                ],
            ]
        );
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('platform remove');
            $printer->info('Usage: platform remove <component> [--delete]');
            $printer->writeln();
            $printer->note('Without --delete the files stay on disk; only platform.json changes.');
        });
    }

    public function execute(Input $input, Context $context): int
    {
        $name = $input->argument('component');

        if ($name === null) {
            throw InvalidInput::missingArgument('component', $this->getDefinition());
        }

        $workspace = $context->requireWorkspace();
        $component = $workspace->manifest->component($name);
        $printer = $context->printer();

        if ($input->flag('delete')) {
            $context->filesystem->delete($workspace->componentPath($component->name));
        }

        $workspace->manifest->without($name)->save($context->filesystem, $workspace->root);

        $printer->success("Component '{$name}' removed from the workspace.");

        if (! $input->flag('delete')) {
            $printer->note("Its directory ({$component->path}/) was kept. Re-add it with: platform add {$name}");
        }

        return 0;
    }
}
