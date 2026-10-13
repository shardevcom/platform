<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;

/**
 * Boundary: one CLI verb.
 *
 * A command owns its name, what it accepts and what it does; the router owns
 * nothing but dispatching. `execute()` returns the process exit code instead of
 * calling exit(), which is what lets a command be asserted in a test.
 */
interface CommandInterface
{
    /**
     * Primary invocation name as typed by the user ('new', 'doctor',
     * 'package make'). May contain spaces for nested commands.
     */
    public function getName(): string;

    /**
     * Alternative names resolved to the same command.
     *
     * @return list<string>
     */
    public function getAliases(): array;

    /**
     * One-line description shown in the command index.
     */
    public function getSummary(): string;

    public function getDefinition(): InputDefinition;

    /**
     * Full help text for this command.
     */
    public function getHelp(): string;

    public function execute(Input $input, Context $context): int;
}