<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Exception\InvalidInput;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;

/**
 * `platform config name <name>`: renames the workspace in platform.json.
 */
final class ConfigNameCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'config name';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getSummary(): string
    {
        return 'Rename the workspace (platform.json name)';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create(arguments: [
            'name' => 'New workspace name (lowercase, no spaces)',
        ]);
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('platform config name');
            $printer->info('Usage: platform config name <name>');
            $printer->writeln();
            $printer->note('Renames the workspace recorded in platform.json.');
        });
    }

    public function execute(Input $input, Context $context): int
    {
        $name = $input->argument('name');

        if ($name === null) {
            throw InvalidInput::missingArgument('name', $this->getDefinition());
        }

        if (preg_match('/^[a-z][a-z0-9_-]*$/', $name) !== 1) {
            throw InvalidInput::create(
                "Invalid workspace name '{$name}'. Use lowercase letters, digits, dashes and underscores, starting with a letter."
            );
        }

        $workspace = $context->requireWorkspace();
        $printer = $context->printer();

        if ($workspace->manifest->name === $name) {
            $printer->info('Already named '.$name.'.');

            return 0;
        }

        $previous = $workspace->manifest->name;
        $workspace->manifest->renamed($name)->save($context->filesystem, $workspace->root);

        $printer->success("Workspace renamed: {$previous} -> {$name}.");

        return 0;
    }
}
