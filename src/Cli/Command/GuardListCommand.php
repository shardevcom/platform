<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;

/**
 * `platform guard list`: the guards a workspace exposes, and who serves them.
 *
 * The list is derived from platform.json (which mirrors the API platforms), so
 * it can never drift from what the workspace actually builds.
 */
final class GuardListCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'guard list';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getSummary(): string
    {
        return 'List the guards the workspace exposes';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create();
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('platform guard list');
            $printer->info('Usage: platform guard list');
            $printer->writeln();
            $printer->note('Lists every guard declared in platform.json and the components that serve it.');
        });
    }

    public function execute(Input $input, Context $context): int
    {
        $workspace = $context->requireWorkspace();
        $printer = $context->printer();
        $guards = $workspace->manifest->guards();

        if ($guards === []) {
            $printer->warning('This workspace declares no guards yet.');
            $printer->note('Add one with: platform guard add <guard>');

            return 0;
        }

        $components = $workspace->manifest->components();
        $rows = [];

        foreach ($guards as $guard) {
            $served = [];

            foreach ($components as $component) {
                if ($component->serves($guard)) {
                    $served[] = $component->name;
                }
            }

            $rows[] = [
                $guard,
                $workspace->manifest->isPublicGuard($guard) ? 'public' : 'private',
                $served === [] ? '—' : implode(', ', $served),
            ];
        }

        $printer->table(['Guard', 'Visibility', 'Served by'], $rows);

        return 0;
    }
}
