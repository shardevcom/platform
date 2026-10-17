<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor;

use Shardevcom\Cli\Process\ProcessResult;
use Shardevcom\Cli\Process\ProcessRunner;
use Shardevcom\Cli\Workspace\Component;
use Shardevcom\Cli\Workspace\DriverRegistry;
use Shardevcom\Cli\Workspace\Requirement;
use Shardevcom\Cli\Workspace\Workspace;

/**
 * Checks that every installed client has the tools it needs to build and run.
 *
 * The requirements are declared by each component driver, so a workspace with
 * only an API runs no extra checks, and adding a client type adds its toolchain
 * checks without touching the doctor.
 */
final class RequirementsSuite implements CheckSuite
{
    public function __construct(
        private readonly Workspace $workspace,
        private readonly DriverRegistry $drivers,
        private readonly ProcessRunner $process,
    ) {}

    public function name(): string
    {
        return 'Client requirements';
    }

    /**
     * @return list<CheckResult>
     */
    public function run(): array
    {
        $results = [];

        foreach ($this->workspace->manifest->enabled() as $component) {
            $path = $this->workspace->componentPath($component->name);
            $driver = $this->drivers->driver($component->type);

            foreach ($driver->requirements() as $requirement) {
                $results[] = $this->check($component, $requirement, $path);
            }
        }

        if ($results === []) {
            $results[] = CheckResult::pass('Clients', 'No client components installed.');
        }

        return $results;
    }

    private function check(Component $component, Requirement $requirement, string $path): CheckResult
    {
        $result = $this->process->run($requirement->command, $path);
        $label = $component->name.': '.$requirement->label;

        $probed = $result->successful()
            && ($requirement->expect === '' || $result->contains($requirement->expect));

        return $probed
            ? CheckResult::pass($label, $this->version($result))
            : CheckResult::fail($label, $requirement->hint);
    }

    private function version(ProcessResult $result): string
    {
        foreach (preg_split('/\R/', $result->output) ?: [] as $line) {
            $line = trim($line);

            if ($line !== '') {
                return strlen($line) > 120 ? substr($line, 0, 117).'...' : $line;
            }
        }

        return 'ok';
    }
}
