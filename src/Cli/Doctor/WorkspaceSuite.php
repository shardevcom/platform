<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor;

use Shardevcom\Cli\Filesystem\Filesystem;
use Shardevcom\Cli\Workspace\Component;
use Shardevcom\Cli\Workspace\Workspace;

/**
 * Checks that every component recorded in platform.json is actually on disk.
 *
 * Without this, `doctor` would only see the API (projectRoot) and silently
 * ignore a client that was never scaffolded or was deleted by hand.
 */
final class WorkspaceSuite implements CheckSuite
{
    public function __construct(
        private readonly Workspace $workspace,
        private readonly Filesystem $filesystem,
    ) {}

    public function name(): string
    {
        return 'Workspace';
    }

    /**
     * @return list<CheckResult>
     */
    public function run(): array
    {
        $results = [];

        foreach ($this->workspace->manifest->enabled() as $component) {
            $results[] = $this->check($component);
        }

        if ($results === []) {
            $results[] = CheckResult::fail('Components', 'The workspace has no enabled components.');
        }

        return $results;
    }

    private function check(Component $component): CheckResult
    {
        $path = $this->workspace->componentPath($component->name);

        if (! $this->filesystem->isDirectory($path)) {
            return CheckResult::fail(
                $component->name.' ('.$component->type->value.')',
                "missing {$component->path}/"
            );
        }

        $installed = $this->filesystem->isDirectory($path.'/'.$component->type->dependenciesMarker());

        return CheckResult::of(
            $component->name.' ('.$component->type->value.')',
            true,
            $installed ? $component->path.'/ (installed)' : $component->path.'/ (run platform install)'
        );
    }
}
