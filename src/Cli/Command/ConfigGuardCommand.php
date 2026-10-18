<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Exception\CommandFailed;
use Shardevcom\Cli\Exception\InvalidInput;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;
use Shardevcom\Cli\Workspace\ComponentType;

/**
 * `platform config guard <component> <list>`: sets the guards a component serves.
 *
 * The API is intentionally excluded: it mirrors the platforms it publishes, so
 * its guard list is changed through `platform guard add`, not here.
 */
final class ConfigGuardCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'config guard';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getSummary(): string
    {
        return 'Set the guards a component serves (comma-separated)';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create(arguments: [
            'component' => 'Component whose guards change',
            'guards' => 'Comma-separated guards (e.g. web,admin)',
        ]);
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('platform config guard');
            $printer->info('Usage: platform config guard <component> <guards>');
            $printer->writeln();
            $printer->note('The guards are stored in platform.json; the apps themselves are not created.');
            $printer->note('For the API use: platform guard add <guard>.');
        });
    }

    public function execute(Input $input, Context $context): int
    {
        $component = $input->argument('component');
        $guards = $input->argument('guards');

        if ($component === null) {
            throw InvalidInput::missingArgument('component', $this->getDefinition());
        }

        if ($guards === null) {
            throw InvalidInput::missingArgument('guards', $this->getDefinition());
        }

        $workspace = $context->requireWorkspace();
        $target = $workspace->manifest->component($component);
        $printer = $context->printer();

        if ($target->type === ComponentType::Api) {
            throw CommandFailed::create('The API mirrors the platforms it publishes; add a guard with: platform guard add <guard>.');
        }

        $list = $this->guards($guards);

        if ($list === $target->guards) {
            $printer->info('Already configured: '.$component.' = '.implode(', ', $list));

            return 0;
        }

        $workspace->manifest->with($target->withGuards($list))->save($context->filesystem, $workspace->root);

        $printer->success("Component '{$component}' now serves: ".implode(', ', $list).'.');
        $printer->note('Run platform dev/build <component> to use the new guards.');

        return 0;
    }

    /**
     * @return list<string>
     */
    private function guards(string $value): array
    {
        $guards = array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $guard): bool => $guard !== ''
        ));

        if ($guards === []) {
            throw InvalidInput::create('Provide at least one guard, e.g. platform config guard web employee.');
        }

        foreach ($guards as $guard) {
            if (preg_match('/^[a-z][a-z0-9_-]*$/', $guard) !== 1) {
                throw InvalidInput::create(
                    "Invalid guard '{$guard}'. Use lowercase letters, digits, dashes and underscores, starting with a letter."
                );
            }
        }

        return $guards;
    }
}
