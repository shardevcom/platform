<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Exception\InvalidInput;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;
use Shardevcom\Cli\Workspace\DockerScaffolder;

/**
 * `platform config enable|disable <component>`: toggles a component in the
 * manifest. Disabled components are skipped by install/build/dev and by the
 * doctor.
 */
final class ConfigToggleCommand implements CommandInterface
{
    public function __construct(private readonly bool $enable) {}

    public function getName(): string
    {
        return 'config '.($this->enable ? 'enable' : 'disable');
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getSummary(): string
    {
        return ($this->enable ? 'Enable' : 'Disable').' a component in the workspace';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create(arguments: [
            'component' => 'Component to '.($this->enable ? 'enable' : 'disable'),
        ]);
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('platform '.$this->getName());
            $printer->info('Usage: platform '.$this->getName().' <component>');
            $printer->writeln();
            $printer->note('Disabled components are skipped by install/build/dev and the doctor.');
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

        if ($component->enabled === $this->enable) {
            $printer->info("Component '{$name}' is already ".($this->enable ? 'enabled' : 'disabled').'.');

            return 0;
        }

        $manifest = $workspace->manifest->with($component->withEnabled($this->enable));
        $manifest->save($context->filesystem, $workspace->root);

        (new DockerScaffolder($context->filesystem))->write($workspace->root, $manifest);

        $printer->success("Component '{$name}' ".($this->enable ? 'enabled' : 'disabled').'.');

        return 0;
    }
}
