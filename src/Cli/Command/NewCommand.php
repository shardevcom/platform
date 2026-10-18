<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Exception\InvalidInput;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;
use Shardevcom\Cli\Workspace\Component;
use Shardevcom\Cli\Workspace\WorkspaceScaffolder;
use Shardevcom\Framework;

/**
 * `platform new <name>`: scaffolds a workspace, not just an API.
 *
 * The workspace root is independent of its members: `--with` picks which
 * components to create (`api`, `web`, `mobile`, `desktop`). The API is a
 * component like any other, so clients can be added or dropped later without
 * touching it. `--no-install` scaffolds clients without running their package
 * managers (the API is always fully installed so the project boots).
 */
final class NewCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'new';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getSummary(): string
    {
        return 'Create a workspace (API + clients) wired to the platform';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create(
            arguments: ['target' => 'Directory to create (lowercase, no spaces)'],
            options: [
                'laravel' => [
                    'description' => 'Laravel version for the API component',
                    'value' => true,
                    'default' => '12',
                ],
                'with' => [
                    'description' => 'Components to create: api,web,mobile,desktop',
                    'value' => true,
                    'default' => 'api',
                ],
                'no-interaction' => [
                    'description' => 'Never prompt Composer',
                    'shortcut' => 'n',
                ],
                'no-install' => [
                    'description' => 'Scaffold clients without running npm/flutter/gradlew',
                ],
            ]
        );
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('platform new');
            $printer->info('Usage: platform new <target> [--with=api,web,mobile,desktop] [--laravel=12] [--no-interaction] [--no-install]');
            $printer->writeln();
            $printer->writeln('  Arguments & options:');
            $printer->table([], $this->getDefinition()->helpRows());
            $printer->writeln();
            $printer->note('The workspace root holds platform.json; the API and every client are independent components.');
            $printer->note('Manage them with: platform add, platform install, platform build, platform dev, platform status.');
        });
    }

    public function execute(Input $input, Context $context): int
    {
        $target = $input->argument('target');

        if ($target === null) {
            throw InvalidInput::missingArgument('target', $this->getDefinition());
        }

        if (preg_match('/^[a-z][a-z0-9_-]*$/', $target) !== 1) {
            throw InvalidInput::create(
                "Invalid project name '{$target}'. Use lowercase letters, digits, dashes and underscores, starting with a letter."
            );
        }

        $laravel = (string) $input->option('laravel', '12');

        if (! Framework::supportsLaravel($laravel)) {
            throw InvalidInput::create(Framework::incompatibilityMessage($laravel));
        }

        $components = $this->components((string) $input->option('with', 'api'));
        $printer = $context->printer();

        $printer->heading("Creating Shardev workspace '{$target}'");
        $printer->writeln();

        $manifest = (new WorkspaceScaffolder($context))->create(
            $context->path($target),
            $components,
            $this->laravelConstraint($laravel),
            $input->flag('no-interaction'),
            ! $input->flag('no-install'),
        );

        $printer->table(['Component', 'Path', 'Guards'], array_map(
            static fn (Component $component): array => [
                $component->name,
                $component->path,
                $component->guards === [] ? '—' : implode(', ', $component->guards),
            ],
            array_values($manifest->components())
        ));
        $printer->writeln();

        $printer->success("Workspace '{$target}' created successfully.");
        $printer->writeln();
        $printer->note('Next steps:');
        $printer->bulletList([
            "cd {$target}",
            'platform status',
            'platform dev api',
        ]);

        return 0;
    }

    /**
     * @return list<Component>
     */
    private function components(string $with): array
    {
        $names = array_values(array_filter(
            array_map('trim', explode(',', $with)),
            static fn (string $name): bool => $name !== ''
        ));

        if ($names === []) {
            throw InvalidInput::create('At least one component is required in --with.');
        }

        return array_map(static fn (string $name): Component => Component::create($name), $names);
    }

    private function laravelConstraint(string $laravel): string
    {
        return preg_match('/^\d+$/', $laravel) === 1 ? '^'.$laravel.'.0' : $laravel;
    }
}
