<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Command;

/**
 * Boundary: a command that owns sub-commands ('package make').
 *
 * Implemented by group commands so the router can walk one level down without
 * a second routing mechanism.
 */
interface GroupCommandInterface extends CommandInterface
{
    /**
     * @return array<string, CommandInterface> keyed by sub-command name
     */
    public function subCommands(): array;
}