<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;
use Shardevcom\Cli\Scaffold\DemoInstaller;

/**
 * `platform demo list`: what the framework ships as demo.
 */
final class DemoListCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'demo list';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getSummary(): string
    {
        return 'List the packages the demo application ships';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create();
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('platform demo list');
            $printer->info('Usage: platform demo list');
        });
    }

    public function execute(Input $input, Context $context): int
    {
        $printer = $context->printer();
        $available = (new DemoInstaller($context))->available();

        $printer->heading('Demo application packages');

        if ($available === []) {
            $printer->warning('The framework ships no demo packages.');

            return 0;
        }

        $printer->writeln();
        $printer->bulletList($available);
        $printer->writeln();
        $printer->note('Install the reference module with: platform demo add product');

        return 0;
    }
}

