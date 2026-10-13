<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor;

/**
 * Boundary: a group of related health checks.
 *
 * Suites exist so `doctor` reads as a list of concerns (toolchain, project
 * files, packages, database, running app) and each can be tested with fakes.
 */
interface CheckSuite
{
    /**
     * Human name of the group, shown as a section in the doctor report.
     */
    public function name(): string;

    /**
     * @return list<CheckResult>
     */
    public function run(): array;
}