<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Console\Printer;
use Shardevcom\Cli\Doctor\ApiSuite;
use Shardevcom\Cli\Doctor\ApplicationSuite;
use Shardevcom\Cli\Doctor\AuthenticationSuite;
use Shardevcom\Cli\Doctor\CheckResult;
use Shardevcom\Cli\Doctor\CheckSuite;
use Shardevcom\Cli\Doctor\DatabaseSuite;
use Shardevcom\Cli\Doctor\EnvironmentSuite;
use Shardevcom\Cli\Doctor\PackageIntegrationSuite;
use Shardevcom\Cli\Doctor\PackagesSuite;
use Shardevcom\Cli\Doctor\ProjectSuite;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;
use Shardevcom\Cli\Packages\PackageCatalog;

/**
 * `platform doctor`: inspects the project in the current directory.
 *
 * The checks live in CheckSuite objects so this command is only responsible
 * for collecting and rendering results.
 */
final class DoctorCommand implements CommandInterface
{
    /**
     * @param list<CheckSuite> $suites
     */
    public function __construct(
        private readonly Context $context,
        private readonly array $suites = [],
    ) {}

    public function getName(): string
    {
        return 'doctor';
    }

    public function getAliases(): array
    {
        return ['health'];
    }

    public function getSummary(): string
    {
        return 'Check that a generated project is healthy';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create();
    }

    public function getHelp(): string
    {
        return Printer::capture(static function (Printer $printer): void {
            $printer->heading('platform doctor');
            $printer->info('Usage: platform doctor');
            $printer->note('Runs from a generated project directory (walks up to its composer.json).');
        });
    }

    public function execute(Input $input, Context $context): int
    {
        $printer = $context->printer();
        $groups = $this->groups();
        $showSuites = count($groups) > 1;

        $printer->heading('Shardev Platform Doctor');

        $all = [];

        foreach ($groups as $group) {
            if ($showSuites) {
                $printer->section($group['suite']);
            }

            $printer->table(['Result', 'Check', 'Detail'], array_map(
                static fn (CheckResult $result): array => $result->toRow(),
                $group['results']
            ));
            $printer->writeln();

            $all = [...$all, ...$group['results']];
        }

        $failed = array_values(array_filter(
            $all,
            static fn (CheckResult $result): bool => ! $result->passed
        ));

        if ($failed === []) {
            $printer->success('Application is ready.');

            return 0;
        }

        $printer->failure(sprintf('%d of %d checks failed.', count($failed), count($all)));

        foreach ($failed as $result) {
            $printer->note($result->label.': '.$result->detail);
        }

        return 1;
    }

    /**
     * Runs every suite, keeping a crashing suite visible as a failed check
     * instead of losing the checks that follow it.
     *
     * @return list<array{suite: string, results: list<CheckResult>}>
     */
    private function groups(): array
    {
        $groups = [];

        foreach ($this->suites() as $suite) {
            try {
                $results = $suite->run();
            } catch (\Throwable $exception) {
                $results = [CheckResult::fail(
                    'Suite crashed',
                    $suite->name().': '.$exception->getMessage()
                )];
            }

            $groups[] = ['suite' => $suite->name(), 'results' => array_values($results)];
        }

        return $groups;
    }

    /**
     * @return list<CheckSuite>
     */
    private function suites(): array
    {
        if ($this->suites !== []) {
            return $this->suites;
        }

        $projectRoot = $this->context->projectRoot();
        $filesystem = $this->context->filesystem;
        $catalog = $this->catalogFor($projectRoot);

        return [
            new EnvironmentSuite($filesystem, $this->context->process, $projectRoot),
            new ProjectSuite($filesystem, $projectRoot),
            new PackagesSuite($filesystem, $catalog, $projectRoot),
            new PackageIntegrationSuite($filesystem, $catalog, $this->context->process, $projectRoot),
            new DatabaseSuite($filesystem, $projectRoot),
            new ApplicationSuite($this->context->process, $projectRoot),
            new ApiSuite($this->context->process, $filesystem, $projectRoot),
            new AuthenticationSuite($this->context->process, $filesystem, $projectRoot),
        ];
    }

    /**
     * An installed project ships its own copy of the packages, so doctor must
     * inspect that copy. Falling back to the framework root keeps the command
     * useful when it is run from the framework itself.
     */
    private function catalogFor(string $projectRoot): PackageCatalog
    {
        $filesystem = $this->context->filesystem;

        return $filesystem->isDirectory($projectRoot.'/packages')
            ? new PackageCatalog($filesystem, $projectRoot)
            : $this->context->packageCatalog();
    }
}