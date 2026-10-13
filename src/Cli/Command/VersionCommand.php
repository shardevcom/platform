<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;
use Shardevcom\Framework;

/**
 * Prints the framework version.
 */
final class VersionCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'version';
    }

    public function getAliases(): array
    {
        return ['-V', '--version'];
    }

    public function getSummary(): string
    {
        return 'Show the framework version';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create();
    }

    public function getHelp(): string
    {
        return Printer::capture(static function (Printer $printer): void {
            $printer->heading('Shardev Platform');
            $printer->info('Usage: platform --version');
        });
    }

    public function execute(Input $input, Context $context): int
    {
        $context->output->writeln('Shardev Platform '.Framework::version());

        return 0;
    }
}