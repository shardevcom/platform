<?php

declare(strict_types=1);

namespace Tests\Unit\Cli\Support;

use Shardevcom\Cli\Console\Printer;

/**
 * A Printer wired to memory streams, so command output can be asserted.
 */
final class RecordingPrinter
{
    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    private Printer $printer;

    private function __construct(bool $decorated = false, int $width = 78)
    {
        $this->stdout = fopen('php://memory', 'wb+');
        $this->stderr = fopen('php://memory', 'wb+');
        $this->printer = Printer::toStreams($this->stdout, $this->stderr, $decorated, $width);
    }

    public static function create(bool $decorated = false, int $width = 78): self
    {
        return new self($decorated, $width);
    }

    public function printer(): Printer
    {
        return $this->printer;
    }

    public function stdout(): string
    {
        return $this->contents($this->stdout);
    }

    public function stderr(): string
    {
        return $this->contents($this->stderr);
    }

    public function output(): string
    {
        return $this->stdout().$this->stderr();
    }

    /**
     * @param resource $stream
     */
    private function contents($stream): string
    {
        rewind($stream);

        return (string) stream_get_contents($stream);
    }
}