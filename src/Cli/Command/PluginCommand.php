<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Exception\InvalidInput;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;

/**
 * `platform plugin ...` namespace.
 *
 * Plugins are API packages that declare a `plugin.json` manifest (resource,
 * actions, per-guard views). This namespace is the developer-facing view of
 * what the project installed.
 */
final class PluginCommand implements GroupCommandInterface
{
    public function getName(): string
    {
        return 'plugin';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getSummary(): string
    {
        return 'Inspect the plugins installed in this project';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create(
            arguments: ['sub-command' => 'One of: '.implode(', ', array_keys($this->subCommands()))]
        );
    }

    /**
     * @return array<string, CommandInterface>
     */
    public function subCommands(): array
    {
        return [
            'list' => new PluginListCommand(),
            'show' => new PluginShowCommand(),
        ];
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('platform plugin');
            $printer->info('Usage: platform plugin [list | show <name>]');
            $printer->writeln();
            $printer->writeln('  Sub-commands:');

            $rows = [];

            foreach ($this->subCommands() as $name => $command) {
                $rows[] = [$name, $command->getSummary()];
            }

            $printer->table([], $rows);
        });
    }

    public function execute(Input $input, Context $context): int
    {
        $requested = $input->argument('sub-command');

        if ($requested === null) {
            $context->output->writeln($this->getHelp());

            return 0;
        }

        throw InvalidInput::create(
            "Unknown plugin sub-command '{$requested}'. Expected one of: "
            .implode(', ', array_keys($this->subCommands()))
        );
    }
}
