<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace;

/**
 * Boundary: one external tool a component needs to build or run.
 *
 * Pure data (label, probe command, expected output marker, install hint) so a
 * driver can declare its toolchain without knowing how the doctor runs or
 * renders it. An empty `expect` means a successful exit is enough.
 *
 * `locations` lists typical absolute paths of the executable (a leading `~` is
 * the home directory). The doctor probes them when the tool is missing from
 * PATH to suggest the exact `export PATH=...` line instead of a bare "install".
 */
final class Requirement
{
    /**
     * @param list<string> $locations
     */
    public function __construct(
        public readonly string $label,
        public readonly string $command,
        public readonly string $expect,
        public readonly string $hint,
        public readonly array $locations = [],
    ) {}
}
