<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Exception\InvalidInput;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;
use Shardevcom\Cli\Plugins\PluginCatalog;
use Shardevcom\Cli\Plugins\PluginManifest;

/**
 * `platform plugin show <name>`: everything a developer needs to work on a
 * plugin without opening the SPA — the resource actions, the abilities a token
 * must carry, and the route each guard mounts.
 */
final class PluginShowCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'plugin show';
    }

    public function getAliases(): array
    {
        return ['plugin:show'];
    }

    public function getSummary(): string
    {
        return 'Show one plugin: actions, abilities and per-guard routes';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create(
            arguments: ['name' => 'Plugin name (see: platform plugin list)'],
            options: [
                'json' => ['description' => 'Emit the plugin as JSON'],
            ],
        );
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('platform plugin show');
            $printer->info('Usage: platform plugin show <name> [--json]');
        });
    }

    public function execute(Input $input, Context $context): int
    {
        $name = $input->argument('name');

        if ($name === null) {
            throw InvalidInput::create('Missing plugin name. Usage: platform plugin show <name>');
        }

        $catalog = new PluginCatalog($context->filesystem, $context->packageCatalog());
        $plugin = $catalog->find($name);

        if ($plugin === null) {
            $available = array_map(static fn (PluginManifest $p): string => $p->name, $catalog->all());

            throw InvalidInput::create(
                "Unknown plugin '{$name}'. Installed: "
                .($available === [] ? 'none' : implode(', ', $available))
            );
        }

        if ($input->flag('json')) {
            $context->output->writeln(
                (string) json_encode($plugin->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            return 0;
        }

        $printer = $context->printer();
        $printer->heading($plugin->title.' ('.$plugin->name.')');
        $printer->writeln();
        $printer->table([], [
            ['Resource', $plugin->resource],
            ['Actions', $plugin->actions === [] ? '—' : implode(', ', $plugin->actions)],
        ]);

        $printer->writeln();
        $printer->section('Abilities');
        $printer->bulletList($plugin->abilities() === [] ? ['—'] : $plugin->abilities());

        $printer->writeln();
        $printer->section('Guards');
        $printer->table(['Guard', 'Mode', 'Path'], $this->guardRows($plugin));

        return 0;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function guardRows(PluginManifest $plugin): array
    {
        $rows = [];

        foreach ($plugin->guards as $guard) {
            $rows[] = [$guard->name, $guard->mode, $guard->path ?? '—'];
        }

        return $rows === [] ? [['—', '—', '—']] : $rows;
    }
}
