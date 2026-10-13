<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Exception\InvalidInput;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;

/**
 * `platform package ...` namespace.
 *
 * Group command: it owns no behaviour of its own, it lists and forwards to its
 * sub-commands so the router can resolve `package make` generically.
 */
final class PackageCommand implements GroupCommandInterface
{
    public function getName(): string
    {
        return 'package';
    }

    public function getAliases(): array
    {
        return ['pkg'];
    }

    public function getSummary(): string
    {
        return 'Manage platform packages';
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
            'make' => new PackageMakeCommand(),
            'list' => new ListCommand(),
        ];
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('platform package');
            $printer->info('Usage: platform package <sub-command> [options]');
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

        throw InvalidInput::create("Unknown package sub-command '{$requested}'. Expected one of: ".implode(', ', array_keys($this->subCommands())));
    }
}