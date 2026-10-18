<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Filesystem\Filesystem;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;
use Shardevcom\Cli\Workspace\Component;
use Shardevcom\Cli\Workspace\Workspace;

/**
 * `platform status`: one table describing every component of the workspace.
 */
final class StatusCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'status';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getSummary(): string
    {
        return 'Show the workspace components and their state';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create();
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('platform status');
            $printer->info('Usage: platform status');
            $printer->writeln();
            $printer->note('Lists each component, its path and whether it is scaffolded and installed.');
        });
    }

    public function execute(Input $input, Context $context): int
    {
        $workspace = $context->requireWorkspace();
        $printer = $context->printer();
        $filesystem = $context->filesystem;

        $printer->heading('Workspace: '.$workspace->manifest->name);
        $printer->writeln();
        $printer->table(
            ['Component', 'Type', 'Path', 'Guards', 'Scaffolded', 'Installed'],
            array_map(
                static fn (Component $component): array => self::row($workspace, $component, $filesystem),
                array_values($workspace->manifest->components())
            )
        );

        return 0;
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}
     */
    private static function row(Workspace $workspace, Component $component, Filesystem $filesystem): array
    {
        $path = $workspace->componentPath($component->name);
        $scaffolded = $filesystem->isDirectory($path);
        $installed = $scaffolded
            && $filesystem->isDirectory($path.'/'.$component->type->dependenciesMarker());

        return [
            $component->name.($component->enabled ? '' : ' (off)'),
            $component->type->value,
            $component->path,
            $component->guards === [] ? '—' : implode(', ', $component->guards),
            $scaffolded ? 'yes' : 'no',
            $installed ? 'yes' : 'no',
        ];
    }
}
