<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Exception\InvalidInput;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;
use Shardevcom\Cli\Workspace\ComponentSelector;
use Shardevcom\Cli\Workspace\ComponentType;
use Shardevcom\Cli\Workspace\DriverRegistry;

/**
 * `platform dev <component>`: runs the component's dev process.
 *
 * `php artisan serve`, `npm run dev:<guard>`, `flutter run` or
 * `./gradlew :apps:<guard>:run` depending on the component type.
 */
final class DevCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'dev';
    }

    public function getAliases(): array
    {
        return ['serve'];
    }

    public function getSummary(): string
    {
        return 'Run the dev process for one component';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create(
            arguments: ['component' => 'Component to run: '.implode(', ', ComponentType::names())],
            options: [
                'guard' => [
                    'description' => 'Guard to run (defaults to the component\'s primary guard)',
                    'value' => true,
                ],
            ]
        );
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('platform dev');
            $printer->info('Usage: platform dev <component> [--guard=admin]');
            $printer->writeln();
            $printer->note('Runs the component toolchain in the foreground (artisan serve, npm run dev:<guard>, flutter run, gradlew run).');
        });
    }

    public function execute(Input $input, Context $context): int
    {
        $name = $input->argument('component');

        if ($name === null) {
            throw InvalidInput::missingArgument('component', $this->getDefinition());
        }

        $workspace = $context->requireWorkspace();
        $component = $workspace->manifest->component($name);
        $printer = $context->printer();
        $guard = ComponentSelector::guard($component, $input->option('guard'));

        $label = $guard === null ? $component->name : $component->name.':'.$guard;
        $printer->heading("Starting {$label} ({$component->type->value})");

        (new DriverRegistry($context))
            ->driver($component->type)
            ->dev($component, $workspace->componentPath($component->name), $guard);

        return 0;
    }
}
