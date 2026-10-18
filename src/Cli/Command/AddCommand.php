<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Exception\InvalidInput;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;
use Shardevcom\Cli\Workspace\Component;
use Shardevcom\Cli\Workspace\ComponentType;
use Shardevcom\Cli\Workspace\PlatformsReader;
use Shardevcom\Cli\Workspace\Workspace;
use Shardevcom\Cli\Workspace\WorkspaceScaffolder;
use Shardevcom\Framework;

/**
 * `platform add <component>`: extends an existing workspace.
 *
 * Scaffolds the component directory from its reference template (or the API via
 * composer) and records it in platform.json, so `install`, `build`, `dev` and
 * `status` pick it up automatically.
 */
final class AddCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'add';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getSummary(): string
    {
        return 'Add a component (api, web, mobile, desktop) to the workspace';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create(
            arguments: ['component' => 'Component to add: '.implode(', ', ComponentType::names())],
            options: [
                'guards' => [
                    'description' => 'Comma-separated guards this client serves (defaults to the API platforms)',
                    'value' => true,
                ],
                'path' => [
                    'description' => 'Directory for the component (defaults to its name)',
                    'value' => true,
                ],
                'laravel' => [
                    'description' => 'Laravel version when adding the api component',
                    'value' => true,
                    'default' => '12',
                ],
                'no-interaction' => [
                    'description' => 'Never prompt Composer',
                    'shortcut' => 'n',
                ],
                'no-install' => [
                    'description' => 'Scaffold without running the component package manager',
                ],
            ]
        );
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('platform add');
            $printer->info('Usage: platform add <component> [--guards=web,admin] [--path=web] [--no-install]');
            $printer->writeln();
            $printer->writeln('  Arguments & options:');
            $printer->table([], $this->getDefinition()->helpRows());
            $printer->writeln();
            $printer->note('Run it from the workspace root; the component is recorded in platform.json.');
            $printer->note('Without --guards the component mirrors the guards the API exposes.');
        });
    }

    public function execute(Input $input, Context $context): int
    {
        $name = $input->argument('component');

        if ($name === null) {
            throw InvalidInput::missingArgument('component', $this->getDefinition());
        }

        $type = ComponentType::fromName($name);
        $workspace = $context->requireWorkspace();
        $component = new Component(
            name: $name,
            type: $type,
            path: $input->option('path') ?? $name,
            guards: $this->guards($input, $context, $workspace, $type),
        );

        $printer = $context->printer();

        $printer->heading("Adding '{$component->name}' to the workspace");

        (new WorkspaceScaffolder($context))->add(
            $workspace,
            $component,
            $this->laravelConstraint((string) $input->option('laravel', '12')),
            $input->flag('no-interaction'),
            ! $input->flag('no-install'),
        );

        $printer->success("Component '{$component->name}' added at {$component->path}/.");
        $printer->note('Next: platform install '.$component->name);

        return 0;
    }

    /**
     * @return list<string>
     */
    private function guards(Input $input, Context $context, Workspace $workspace, ComponentType $type): array
    {
        $option = $input->option('guards');

        if ($option !== null) {
            return array_values(array_filter(
                array_map('trim', explode(',', $option)),
                static fn (string $guard): bool => $guard !== ''
            ));
        }

        $api = $workspace->manifest->has('api') ? $workspace->componentPath('api') : null;
        $guards = $api !== null ? (new PlatformsReader($context->filesystem))->keys($api) : [];

        return $guards !== [] ? $guards : $type->defaultGuards();
    }

    private function laravelConstraint(string $laravel): string
    {
        return preg_match('/^\d+$/', $laravel) === 1 ? '^'.$laravel.'.0' : $laravel;
    }
}
