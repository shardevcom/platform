<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Exception\InvalidInput;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;
use Shardevcom\Cli\Workspace\GuardDefinition;
use Shardevcom\Cli\Workspace\GuardScaffolder;

/**
 * `platform guard add <guard>`: adds a guard to a workspace end-to-end.
 *
 * Registers the platform in the API (config/platforms.php + model + migrations),
 * mirrors it into platform.json and scaffolds one app per client. The API
 * stays the source of truth: `guard list` and every build read the guards back
 * from the manifest / config rather than from a hardcoded set.
 */
final class GuardAddCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'guard add';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getSummary(): string
    {
        return 'Add a guard: API platform, model, migrations and one client app per guard';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create(
            arguments: ['guard' => 'Guard slug to add (lowercase, e.g. partner)'],
            options: [
                'name' => ['description' => 'Human name (default: <Guard> Users)', 'value' => true],
                'model' => ['description' => 'User model class name (default: <Guard>User)', 'value' => true],
                'table' => ['description' => 'Database table (default: <guard>s)', 'value' => true],
                'driver' => [
                    'description' => 'Guard driver: passport or session',
                    'value' => true,
                    'default' => 'passport',
                ],
                'prefix' => ['description' => 'URL prefix (default: <guard>)', 'value' => true],
                'no-clients' => [
                    'description' => 'Only register the guard in the API and platform.json',
                ],
                'no-migrate' => [
                    'description' => 'Do not run the API migration',
                ],
            ]
        );
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('platform guard add');
            $printer->info('Usage: platform guard add <guard> [--name=...] [--driver=passport] [--no-clients] [--no-migrate]');
            $printer->writeln();
            $printer->writeln('  Arguments & options:');
            $printer->table([], $this->getDefinition()->helpRows());
            $printer->writeln();
            $printer->note('Passport guards are the default; pick --driver=session for a first-party web guard.');
            $printer->note('Run it from the workspace root; clients clone their current app for the new guard.');
        });
    }

    public function execute(Input $input, Context $context): int
    {
        $key = $input->argument('guard');

        if ($key === null) {
            throw InvalidInput::missingArgument('guard', $this->getDefinition());
        }

        $guard = GuardDefinition::create(
            key: $this->validateKey($key),
            name: $input->option('name'),
            model: $this->validateModel($input->option('model')),
            table: $input->option('table'),
            driver: $this->validateDriver((string) $input->option('driver', 'passport')),
            prefix: $input->option('prefix'),
        );

        $workspace = $context->requireWorkspace();
        $printer = $context->printer();

        $printer->heading("Adding guard '{$guard->key}'");

        $written = (new GuardScaffolder($context))->add(
            $workspace,
            $guard,
            ! $input->flag('no-clients'),
            ! $input->flag('no-migrate'),
        );

        foreach ($written as $path) {
            $printer->bulletList([$path]);
        }

        $printer->success("Guard '{$guard->key}' registered across the workspace.");
        $printer->note('Next: platform build to rebuild the clients with the new guard.');

        return 0;
    }

    private function validateKey(string $key): string
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $key) !== 1) {
            throw InvalidInput::create(
                "Invalid guard '{$key}'. Use a lowercase slug starting with a letter (a-z, 0-9, _)."
            );
        }

        return $key;
    }

    private function validateModel(?string $model): ?string
    {
        if ($model !== null && preg_match('/^[A-Z][A-Za-z0-9]*$/', $model) !== 1) {
            throw InvalidInput::create("Invalid model '{$model}'. Use a StudlyCase class name.");
        }

        return $model;
    }

    private function validateDriver(string $driver): string
    {
        if (! in_array($driver, ['passport', 'session'], true)) {
            throw InvalidInput::create("Invalid driver '{$driver}'. Expected one of: passport, session.");
        }

        return $driver;
    }
}
