<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Closure;
use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;

/**
 * Prints the command index.
 *
 * The command list arrives through a closure (not a constructor array) because
 * the router needs HelpCommand to answer a bare `platform` before the command
 * list has been built.
 */
final class HelpCommand implements CommandInterface
{
    /**
     * @param Closure(): list<CommandInterface> $commands
     */
    public function __construct(private readonly Closure $commands)
    {}

    public function getName(): string
    {
        return 'help';
    }

    public function getAliases(): array
    {
        return ['--help', '-h'];
    }

    public function getSummary(): string
    {
        return 'Show this help';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create();
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('Shardev Platform CLI');
            $printer->info('Usage: platform <command> [options]');
            $printer->writeln();
            $printer->writeln('  Commands:');

            $rows = [];

            foreach ($this->commands() as $command) {
                $rows[] = [$command->getName(), $command->getSummary()];
            }

            $printer->table([], $rows);
            $printer->writeln();
            $printer->note("Run 'platform <command> --help' for the options of a single command.");
        });
    }

    public function execute(Input $input, Context $context): int
    {
        $context->output->writeln($this->getHelp());

        return 0;
    }

    /**
     * @return list<CommandInterface>
     */
    private function commands(): array
    {
        return array_values(array_filter(
            ($this->commands)(),
            static fn (CommandInterface $command): bool => $command->getName() !== 'help'
        ));
    }
}