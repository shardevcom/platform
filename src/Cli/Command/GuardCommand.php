<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Exception\InvalidInput;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;

/**
 * `platform guard ...` namespace.
 *
 * Group command: it owns no behaviour of its own, it lists and forwards to its
 * sub-commands so the router can resolve `guard add` generically.
 */
final class GuardCommand implements GroupCommandInterface
{
    public function getName(): string
    {
        return 'guard';
    }

    public function getAliases(): array
    {
        return ['guards'];
    }

    public function getSummary(): string
    {
        return 'Manage the authentication guards the workspace exposes';
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
            'add' => new GuardAddCommand(),
            'list' => new GuardListCommand(),
        ];
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('platform guard');
            $printer->info('Usage: platform guard <sub-command> [options]');
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

        throw InvalidInput::create("Unknown guard sub-command '{$requested}'. Expected one of: ".implode(', ', array_keys($this->subCommands())));
    }
}
