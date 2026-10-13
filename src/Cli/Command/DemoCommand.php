<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Exception\InvalidInput;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;
use Shardevcom\Cli\Scaffold\DemoInstaller;

/**
 * `platform demo ...` namespace.
 *
 * Installs the reference demo module into the project being configured, into
 * the packages directory and vendor that config/platform.php declares.
 */
final class DemoCommand implements GroupCommandInterface
{
    public function getName(): string
    {
        return 'demo';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getSummary(): string
    {
        return 'Install the reference demo into this project';
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
            'list' => new DemoListCommand(),
            'add' => new DemoAddCommand(),
        ];
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('platform demo');
            $printer->info('Usage: platform demo [list | add <package>]');
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
            "Unknown demo sub-command '{$requested}'. Expected one of: "
            .implode(', ', array_keys($this->subCommands()))
        );
    }
}
