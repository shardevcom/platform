<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Exception\InvalidInput;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;
use Shardevcom\Cli\Scaffold\ComposerEditor;
use Shardevcom\Cli\Scaffold\PackageScaffold;

/**
 * Scaffolds a new platform package under packages/<vendor>/<name>.
 *
 * All filesystem writes go through PackageScaffold, so `--dry-run` and the
 * real run share one description of what a package looks like.
 */
final class PackageMakeCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'package make';
    }

    public function getAliases(): array
    {
        return ['package:make'];
    }

    public function getSummary(): string
    {
        return 'Scaffold a new platform package';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create(
            arguments: ['name' => 'Package name (lowercase, no slashes)'],
            options: [
                'dry-run' => ['description' => 'Print what would be created', 'shortcut' => 'd'],
                'vendor' => ['description' => 'Vendor directory (defaults to the project config)', 'value' => true],
            ]
        );
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('platform package make');
            $printer->info('Usage: '.$this->getDefinition()->usage('platform package make'));
            $printer->writeln();
            $printer->writeln('  Arguments & options:');
            $printer->table([], $this->getDefinition()->helpRows());
            $printer->writeln();
            $printer->note('The new package is discovered automatically: its namespace and provider are read');
            $printer->note('from the generated composer.json, no installer change required.');
        });
    }

    public function execute(Input $input, Context $context): int
    {
        $name = $input->argument('name');

        if ($name === null) {
            throw InvalidInput::missingArgument('name', $this->getDefinition());
        }

        $printer = $context->printer();
        $config = $context->config();
        $scaffold = new PackageScaffold($context->filesystem, $context->projectRoot(), $config);

        $plan = $scaffold->plan($name, $input->option('vendor'));

        if ($input->flag('dry-run')) {
            $printer->heading("Dry run: package '{$name}'");
            $printer->writeln('  Directories:');
            $printer->bulletList($plan['directories']);
            $printer->writeln('  Files:');
            $printer->bulletList(array_keys($plan['files']));

            return 0;
        }

        $printer->heading("Creating package '{$name}'");
        $scaffold->create($plan);
        $printer->success("Package '{$name}' created at {$plan['root']}");

        $wired = $this->wireComposer($context, $printer);
        $printer->writeln();
        $printer->note('Next steps:');
        $printer->bulletList([
            "Implement the package logic in {$plan['root']}/src",
            'Register the provider in bootstrap/providers.php',
        ]);

        if ($wired) {
            $printer->success('composer.json updated; the namespace resolves after dump-autoload.');
        } else {
            $printer->note("Run 'composer dump-autoload' so the namespace resolves");
        }

        return 0;
    }

    /**
     * Registers the new package in the project's composer.json.
     *
     * A package whose namespace is not mapped is invisible to the autoloader,
     * so leaving this to the developer is how a freshly created package ends
     * up reported as unmapped by `platform doctor`.
     *
     * @return bool false when the command runs outside an installed project
     */
    private function wireComposer(Context $context, Printer $printer): bool
    {
        if (! $context->isInstalledProject()) {
            return false;
        }

        $projectRoot = $context->projectRoot();

        (new ComposerEditor($context->filesystem, $context->projectCatalog()))
            ->configure($projectRoot);

        $context->process->run('composer dump-autoload', $projectRoot);

        return true;
    }
}