<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace;

/**
 * Boundary: one external tool a component needs to build or run.
 *
 * Pure data (label, probe command, expected output marker, install hint) so a
 * driver can declare its toolchain without knowing how the doctor runs or
 * renders it. An empty `expect` means a successful exit is enough.
 */
final class Requirement
{
    public function __construct(
        public readonly string $label,
        public readonly string $command,
        public readonly string $expect,
        public readonly string $hint,
    ) {}
}
