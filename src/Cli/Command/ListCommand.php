<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;
use Shardevcom\Framework;

/**
 * Renders the discovered packages as a table.
 *
 * The rows come from PackageManifest, so this command cannot disagree with
 * what the application actually loads.
 */
final class ListCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'list';
    }

    public function getAliases(): array
    {
        return ['ls'];
    }

    public function getSummary(): string
    {
        return 'List installed platform packages';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create();
    }

    public function getHelp(): string
    {
        return Printer::capture(static function (Printer $printer): void {
            $printer->heading('platform list');
            $printer->info('Usage: platform list');
            $printer->note('Lists every package discovered under the framework packages directory.');
        });
    }

    public function execute(Input $input, Context $context): int
    {
        $printer = $context->printer();
        $catalog = $context->packageCatalog();
        $packages = $catalog->packages();

        if ($packages === []) {
            $printer->warning('No platform packages found.');

            return 0;
        }

        $printer->heading('Installed platform packages');
        $printer->table(['Package', 'Provider'], array_map(
            static fn ($package): array => [
                $package->key(),
                $package->hasProvider() ? (string) $package->provider : '(no provider)',
            ],
            $packages
        ));

        $printer->writeln();
        $printer->note('Framework '.Framework::version());

        return 0;
    }
}