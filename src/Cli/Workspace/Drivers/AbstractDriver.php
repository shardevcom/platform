<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace\Drivers;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Process\ProcessResult;
use Shardevcom\Cli\Workspace\Component;
use Shardevcom\Cli\Workspace\ComponentDriver;

/**
 * Shared plumbing for the per-type drivers: every command runs through the
 * injected ProcessRunner, so the whole control surface is testable with a fake
 * shell and no real npm/flutter/gradle install.
 */
abstract class AbstractDriver implements ComponentDriver
{
    public function __construct(protected readonly Context $context) {}

    public function verify(Component $component, string $path): bool
    {
        return $this->run($this->verifyCommand($component), $path)->successful();
    }

    protected function run(string $command, string $path): ProcessResult
    {
        return $this->context->process->run($command, $path);
    }

    abstract protected function verifyCommand(Component $component): string;
}
