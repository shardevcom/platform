<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use Shardevcom\Cli\Console\Printer;
use Tests\Unit\Cli\Support\RecordingPrinter;

/**
 * Acceptance for the only place output formatting is allowed to live: plain
 * when not attached to a TTY, aligned tables, and errors on stderr.
 */
final class PrinterTest extends TestCase
{
    public function test_stays_plain_when_ansi_is_not_supported(): void
    {
        $printer = $this->printer();

        $this->assertFalse($printer->isDecorated());
        $this->assertSame('Hello', $printer->style('Hello', 'red'));
    }

    public function test_colours_only_when_the_terminal_supports_it(): void
    {
        $printer = RecordingPrinter::create(decorated: true)->printer();

        $this->assertTrue($printer->isDecorated());
        $this->assertStringContainsString("\033[", $printer->style('Hello', 'red'));
        $this->assertStringContainsString('Hello', $printer->style('Hello', 'red'));
    }

    public function test_ansi_detection_respects_no_color(): void
    {
        $stream = fopen('php://memory', 'wb+');

        $this->assertFalse(Printer::toStreams($stream, $stream)->isDecorated());
    }

    public function test_aligns_table_columns_to_the_widest_cell(): void
    {
        $printer = $this->printer();

        $printer->table(['Name', 'Value'], [
            ['a', '1'],
            ['longer', '2'],
        ]);

        $this->assertSame(
            ['  Name    Value', '  a       1', '  longer  2'],
            $this->linesOf()
        );
    }

    public function test_renders_headers_only_when_there_are_rows(): void
    {
        $printer = $this->printer();

        $printer->table([], []);
        $printer->table([], [['only row']]);

        $this->assertSame(['  only row'], $this->linesOf());
    }

    public function test_prefixes_each_semantic_line_and_keeps_them_distinguishable(): void
    {
        $printer = $this->printer();

        $printer->info('informative');
        $printer->success('successful');
        $printer->warning('careful');
        $printer->failure('broken');
        $printer->note('extra');

        $this->assertSame([
            '  informative',
            '  + successful',
            '  ! careful',
            '  x broken',
            '  extra',
        ], $this->linesOf());
    }

    public function test_heading_underlines_within_the_terminal_width(): void
    {
        $recorder = RecordingPrinter::create(decorated: false, width: 10);

        $recorder->printer()->heading('Title');

        $this->assertSame(['', 'Title', '=====', '', ''], explode("\n", $recorder->stdout()));
    }

    public function test_reports_progress_as_a_counter(): void
    {
        $printer = $this->printer();

        $printer->step(2, 15, 'Copying packages');

        $this->assertStringContainsString('[2/15]', $this->recorder()->stdout());
        $this->assertStringContainsString('Copying packages', $this->recorder()->stdout());
    }

    public function test_errors_go_to_stderr_not_stdout(): void
    {
        $recorder = RecordingPrinter::create();

        $recorder->printer()->errorln('broken pipe');

        $this->assertSame('', $recorder->stdout());
        $this->assertStringContainsString('broken pipe', $recorder->stderr());
    }

    public function test_capture_returns_formatted_text_without_touching_the_terminal(): void
    {
        $captured = Printer::capture(static function (Printer $printer): void {
            $printer->heading('Help');
            $printer->info('Usage: platform list');
        });

        $this->assertStringContainsString('Help', $captured);
        $this->assertStringContainsString('Usage: platform list', $captured);
        $this->assertStringNotContainsString("\033[", $captured);
    }

    private function recorder(): RecordingPrinter
    {
        return $this->recorder ??= RecordingPrinter::create();
    }

    private function printer(): Printer
    {
        return $this->recorder()->printer();
    }

    /**
     * @return list<string>
     */
    private function linesOf(): array
    {
        return array_values(array_filter(
            explode("\n", $this->recorder()->stdout()),
            static fn (string $line): bool => $line !== ''
        ));
    }

    private ?RecordingPrinter $recorder = null;
}