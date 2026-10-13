<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Exception\InvalidInput;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;
use Shardevcom\Cli\Scaffold\DemoInstaller;
use Shardevcom\Cli\Scaffold\ProjectScaffolder;
use Shardevcom\Framework;

/**
 * `platform new <project>`: scaffolds a Laravel app wired to the platform.
 *
 * The workflow lives in ProjectScaffolder; this class only validates input,
 * prints the steps and reports the outcome.
 */
final class NewCommand implements CommandInterface
{
    private const STEPS = 19;

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
        return 'Scaffold a new project wired to the platform';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create(
            arguments: ['target' => 'Directory to create (lowercase, no spaces)'],
            options: [
                'laravel' => [
                    'description' => 'Laravel version to install',
                    'value' => true,
                    'default' => '12',
                ],
                'no-interaction' => [
                    'description' => 'Never prompt Composer',
                    'shortcut' => 'n',
                ],
                'demo' => [
                    'description' => 'Install a demo package after creating the project (e.g. product)',
                    'value' => true,
                    'default' => null,
                ],
            ]
        );
    }

    public function getHelp(): string
    {
        return Printer::capture(function (Printer $printer): void {
            $printer->heading('platform new');
            $printer->info('Usage: '.$this->getDefinition()->usage('platform new'));
            $printer->writeln();
            $printer->writeln('  Arguments & options:');
            $printer->table([], $this->getDefinition()->helpRows());
            $printer->writeln();
            $printer->note('Installed packages are discovered automatically; nothing is hardcoded here.');
        });
    }

    public function execute(Input $input, Context $context): int
    {
        $target = $input->argument('target');

        if ($target === null) {
            throw InvalidInput::missingArgument('target', $this->getDefinition());
        }

        $laravel = (string) $input->option('laravel', '12');

        if (! Framework::supportsLaravel($laravel)) {
            throw InvalidInput::create(Framework::incompatibilityMessage($laravel));
        }

        if (preg_match('/^[a-z][a-z0-9_-]*$/', $target) !== 1) {
            throw InvalidInput::create(
                "Invalid project name '{$target}'. Use lowercase letters, digits, dashes and underscores, starting with a letter."
            );
        }

        $printer = $context->printer();
        $scaffolder = new ProjectScaffolder($context);
        $targetPath = $context->path($target);
        $noInteraction = $input->flag('no-interaction');

        $scaffolder->assertTargetIsFree($targetPath);

        $printer->heading("Creating Shardev project '{$target}' with Laravel {$laravel}");

        $step = 0;
        $next = static function () use ($printer, &$step): int {
            return ++$step;
        };

        $printer->step($next(), self::STEPS, 'Creating Laravel project...');
        $scaffolder->createLaravelProject($targetPath, $this->laravelConstraint($laravel), $noInteraction);

        $printer->step($next(), self::STEPS, 'Copying platform packages...');
        $installed = $scaffolder->installPackages($targetPath);
        $printer->note(count($installed).' packages installed.');

        $printer->step($next(), self::STEPS, 'Configuring composer.json (psr-4, path repositories, requirements)...');
        $scaffolder->configureComposer($targetPath);

        $printer->step($next(), self::STEPS, 'Installing dependencies...');
        $scaffolder->installDependencies($targetPath);

        $printer->step($next(), self::STEPS, 'Registering platform service providers...');
        $registered = $scaffolder->registerProviders($targetPath);
        $printer->note(count($registered).' providers registered.');

        $printer->step($next(), self::STEPS, 'Writing config/platform.php...');
        $scaffolder->writePlatformConfig($targetPath);

        $printer->step($next(), self::STEPS, 'Preparing .env and sqlite database...');
        $scaffolder->prepareEnvironment($targetPath, Framework::version());

        $printer->step($next(), self::STEPS, 'Generating application key...');
        $scaffolder->generateKey($targetPath);

        $printer->step($next(), self::STEPS, 'Publishing Passport migrations...');
        $scaffolder->publishPassportMigrations($targetPath);

        $printer->step($next(), self::STEPS, 'Generating Passport keys...');
        $scaffolder->generatePassportKeys($targetPath);

        $printer->step($next(), self::STEPS, 'Running migrations...');
        $scaffolder->migrate($targetPath);;

        $printer->step($next(), self::STEPS, 'Creating personal access clients...');
        $scaffolder->createPersonalAccessClients($targetPath);

        $printer->step($next(), self::STEPS, 'Seeding demo data...');
        $scaffolder->seed($targetPath);

        $printer->step($next(), self::STEPS, 'Regenerating the autoloader...');
        $scaffolder->dumpAutoload($targetPath);

        $printer->step($next(), self::STEPS, 'Verifying REST routes...');
        $printer->step($next(), self::STEPS, 'Verifying GraphQL route...');
        $printer->step($next(), self::STEPS, 'Verifying config/platform.php...');
        $printer->step($next(), self::STEPS, 'Verifying database connection...');
        $scaffolder->verify($targetPath, [
            ['php artisan route:list --path=api/v1', 'api/v1', 'REST routes are missing.'],
            ['php artisan route:list --path=graphql', 'graphql', 'The GraphQL route is missing.'],
            ['php artisan config:show platform', 'platform', 'config/platform.php could not be read.'],
            ['php artisan --version', 'Laravel Framework', 'The generated application does not boot.'],
        ]);

        $demoPackage = $input->option('demo');
        if ($demoPackage !== null) {
            $printer->step($next(), self::STEPS, "Installing demo package '{$demoPackage}'...");
            $installer = new DemoInstaller($context);
            $installed = $installer->install($demoPackage, $targetPath, true);
            if (! empty($installed['skipped'])) {
                $printer->note("Demo package '{$demoPackage}' already exists (installed from official packages).");
            } else {
                $printer->note("Demo '{$demoPackage}' installed successfully.");
            }
        }

        $printer->writeln();
        $printer->success("Project '{$target}' created successfully.");
        $printer->writeln();
        $printer->note('Next steps:');
        $printer->bulletList([
            "cd {$target}",
            'php artisan serve',
            "php {$context->frameworkRoot}/bin/platform doctor",
        ]);

        return 0;
    }

    private function laravelConstraint(string $laravel): string
    {
        return preg_match('/^\d+$/', $laravel) === 1 ? '^'.$laravel.'.0' : $laravel;
    }
}