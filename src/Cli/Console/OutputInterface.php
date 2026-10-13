<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Console;

/**
 * Boundary: the only way a command may write to the terminal.
 *
 * Commands never touch STDOUT/STDERR or `echo` directly, so every command is
 * testable by capturing a stream instead of reading a terminal.
 */
interface OutputInterface
{
    public function write(string $text): void;

    public function writeln(string $text = ''): void;

    public function errorln(string $text = ''): void;

    public function isDecorated(): bool;
}