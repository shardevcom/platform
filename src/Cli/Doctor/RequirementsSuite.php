<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor;

use Shardevcom\Cli\Filesystem\Filesystem;
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
 * checks without touching the doctor. When a tool is missing the suite suggests
 * how to fix it: if it is installed outside PATH it returns the exact
 * `export PATH=...` line, otherwise it falls back to the install hint.
 */
final class RequirementsSuite implements CheckSuite
{
    public function __construct(
        private readonly Workspace $workspace,
        private readonly DriverRegistry $drivers,
        private readonly ProcessRunner $process,
        private readonly Filesystem $filesystem,
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

        if ($probed) {
            return CheckResult::pass($label, $this->version($result));
        }

        if ($this->notOnPath($result)) {
            return CheckResult::fail($label, $this->pathSuggestion($requirement));
        }

        return CheckResult::fail($label, $requirement->hint);
    }

    /**
     * Finds the tool in its typical locations and, if present, returns the PATH
     * line that makes it work; otherwise the install hint.
     */
    private function pathSuggestion(Requirement $requirement): string
    {
        foreach ($requirement->locations as $location) {
            $executable = $this->expand($location);

            if ($this->filesystem->isFile($executable)) {
                return 'not on PATH — found at '.$this->shorten($executable)
                    .'; run: export PATH="'.$this->portable(dirname($executable)).':$PATH"';
            }
        }

        return 'not on PATH — '.$requirement->hint;
    }

    private function notOnPath(ProcessResult $result): bool
    {
        return $result->exitCode === 127
            || str_contains($result->output, 'command not found')
            || str_contains($result->output, 'not recognized as an internal');
    }

    private function expand(string $path): string
    {
        if (! str_starts_with($path, '~/')) {
            return $path;
        }

        $home = $this->home();

        return $home === '' ? $path : $home.'/'.substr($path, 2);
    }

    private function home(): string
    {
        return $_SERVER['HOME'] ?? (getenv('HOME') ?: '');
    }

    private function shorten(string $path): string
    {
        $home = $this->home();

        if ($home !== '' && str_starts_with($path, $home.'/')) {
            return '~'.substr($path, strlen($home));
        }

        return $path;
    }

    /**
     * Same as shorten() but as a shell-expandable value: `~` is not expanded
     * inside double quotes, so the suggested command must use `$HOME`.
     */
    private function portable(string $path): string
    {
        $home = $this->home();

        if ($home !== '' && str_starts_with($path, $home.'/')) {
            return '$HOME'.substr($path, strlen($home));
        }

        return $path;
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
