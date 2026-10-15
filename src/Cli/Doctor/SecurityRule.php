<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor;

use Shardevcom\Cli\Filesystem\Filesystem;
use Shardevcom\Cli\Process\ProcessRunner;

/**
 * Interface for a security rule that can be executed during a security audit.
 */
interface SecurityRule
{
    /**
     * Unique identifier for this rule (e.g., "auth.debug-enabled").
     */
    public function id(): string;

    /**
     * Human-readable title for the rule.
     */
    public function title(): string;

    /**
     * Category of the check (e.g., "auth", "authorization", "api", "jobs", "dependencies").
     */
    public function category(): string;

    /**
     * Default severity if the rule passes/fails.
     */
    public function defaultSeverity(): SecuritySeverity;

    /**
     * Execute the rule and return findings.
     *
     * @return list<SecurityFinding>
     */
    public function check(
        Filesystem $filesystem,
        ProcessRunner $process,
        string $projectRoot
    ): array;
}