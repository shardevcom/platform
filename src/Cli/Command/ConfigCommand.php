<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Exception\InvalidInput;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;

/**
 * `platform config ...` namespace.
 *
 * The packages directory and the vendor directory are stored in
 * config/platform.php so later commands (`package make`, `demo add`,
 * `doctor`) reuse them instead of asking again.
 */
final class ConfigCommand implements GroupCommandInterface
{
    public function getName(): string
    {
        return 'config';
    }

    public function getAliases(): array
    {
        return ['cfg'];
    }

    public function getSummary(): string
    {
        return 'Show or change where packages live in this project';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create(
            arguments: ['sub-command' => 'One of: '.implode(', ', array_keys($this->subCommands()))]
        );
    }

    /**
     * @return array<string, CommandInterface>
     */
    public function subCommands(): array
    {
        return [
            'packages' => new ConfigSetCommand('packages'),
            'vendor' => new ConfigSetCommand('vendor'),
        ];
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('platform config');
            $printer->info('Usage: platform config [packages <dir> | vendor <vendor>]');
            $printer->writeln();
            $printer->writeln('  Sub-commands:');

            $rows = [];

            foreach ($this->subCommands() as $name => $command) {
                $rows[] = [$name, $command->getSummary()];
            }

            $printer->table([], $rows);
            $printer->writeln();
            $printer->note('Values are stored in config/platform.php and reused by every later command.');
        });
    }

    public function execute(Input $input, Context $context): int
    {
        $printer = $context->printer();
        $requested = $input->argument('sub-command');

        if ($requested !== null) {
            throw InvalidInput::create(
                "Unknown config sub-command '{$requested}'. Expected one of: "
                .implode(', ', array_keys($this->subCommands()))
            );
        }

        $config = $context->config();
        $projectRoot = $context->projectRoot();

        $printer->heading('Platform configuration');
        $printer->writeln();
        $printer->table(['Setting', 'Value'], [
            ['Project root', $projectRoot],
            ['Config file', $context->configFile()->path()],
            ['Packages dir', $config->packagesDir],
            ['Vendor', $config->vendorNamespace],
            ['Packages path', $config->packagesPath($projectRoot)],
            ['Installed packages', (string) $context->projectCatalog($config)->count()],
        ]);
        $printer->writeln();
        $printer->note('Change them with: platform config packages <dir> | platform config vendor <vendor>');

        return 0;
    }
}