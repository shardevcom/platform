<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Console;

/**
 * Boundary: the single formatting authority of the CLI (colour, icons,
 * tables). Everything a command prints goes through here, which is why no
 * command hardcodes ANSI escapes or printf padding.
 */
final class Printer implements OutputInterface
{
    /** @var array<string, string> */
    private const COLORS = [
        'reset' => "\033[0m",
        'bold' => "\033[1m",
        'dim' => "\033[2m",
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'cyan' => "\033[36m",
    ];

    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    public function __construct(
        private $stdout,
        private $stderr,
        private bool $decorated,
        private int $width = 78,
    ) {}

    /**
     * Builds a printer for the real terminal: ANSI is used only on a TTY with
     * a colour-capable TERM and without NO_COLOR. Everything else stays plain
     * so piped output and CI logs remain readable.
     *
     * @param resource|null $stdout
     * @param resource|null $stderr
     */
    public static function detect($stdout = null, $stderr = null): self
    {
        $out = $stdout ?? self::openStream('php://output');
        $err = $stderr ?? self::openStream('php://stderr');

        return new self($out, $err, self::supportsAnsi($out));
    }

    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    public static function toStreams($stdout, $stderr, bool $decorated = false): self
    {
        return new self($stdout, $stderr, $decorated);
    }

    /**
     * Renders a callback's output into a string. Used by commands whose
     * contract is `getHelp(): string` while the formatting stays in one place.
     *
     * @param callable(self): void $callback
     */
    public static function capture(callable $callback): string
    {
        $stream = fopen('php://memory', 'wb+');

        if ($stream === false) {
            throw new \RuntimeException('Unable to open memory stream');
        }

        $callback(new self($stream, $stream, false));

        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    public function write(string $text): void
    {
        fwrite($this->stdout, $text);
    }

    public function writeln(string $text = ''): void
    {
        fwrite($this->stdout, $text.PHP_EOL);
    }

    public function errorln(string $text = ''): void
    {
        fwrite($this->stderr, $text.PHP_EOL);
    }

    public function isDecorated(): bool
    {
        return $this->decorated;
    }

    public function style(string $text, string $color): string
    {
        if (! $this->decorated || ! isset(self::COLORS[$color])) {
            return $text;
        }

        return self::COLORS[$color].$text.self::COLORS['reset'];
    }

    public function heading(string $text): void
    {
        $this->writeln();
        $this->writeln($this->style($text, 'bold'));
        $this->writeln($this->style(str_repeat('=', min(mb_strlen($text), $this->width)), 'dim'));
        $this->writeln();
    }

    public function section(string $text): void
    {
        $this->writeln();
        $this->writeln('  '.$this->style($text, 'bold'));
    }

    public function info(string $text): void
    {
        $this->writeln('  '.$this->style($text, 'cyan'));
    }

    public function success(string $text): void
    {
        $this->writeln('  '.$this->icon('ok').' '.$this->style($text, 'green'));
    }

    public function warning(string $text): void
    {
        $this->writeln('  '.$this->icon('warn').' '.$this->style($text, 'yellow'));
    }

    public function failure(string $text): void
    {
        $this->writeln('  '.$this->icon('fail').' '.$this->style($text, 'red'));
    }

    public function step(int $current, int $total, string $text): void
    {
        $this->writeln('  '.$this->style(sprintf('[%d/%d]', $current, $total), 'dim').' '.$text);
    }

    public function note(string $text): void
    {
        $this->writeln('  '.$this->style($text, 'dim'));
    }

    /**
     * @param list<string>            $headers
     * @param list<list<string>>      $rows
     */
    public function table(array $headers, array $rows): void
    {
        if ($headers === [] && $rows === []) {
            return;
        }

        $widths = [];

        foreach (array_merge([$headers], $rows) as $row) {
            foreach (array_values($row) as $index => $cell) {
                $widths[$index] = max($widths[$index] ?? 0, mb_strlen($cell));
            }
        }

        if ($headers !== []) {
            $this->writeln($this->style($this->renderRow($headers, $widths), 'bold'));
        }

        foreach ($rows as $row) {
            $this->writeln($this->renderRow($row, $widths));
        }
    }

    /**
     * @param list<string> $lines
     */
    public function bulletList(array $lines): void
    {
        foreach ($lines as $line) {
            $this->writeln('  - '.$line);
        }
    }

    /**
     * @param array<int, int>    $widths
     * @param array<int, string> $row
     */
    private function renderRow(array $row, array $widths): string
    {
        $cells = [];

        foreach (array_values($row) as $index => $cell) {
            $cells[] = $cell.str_repeat(' ', max(0, ($widths[$index] ?? 0) - mb_strlen($cell)));
        }

        return '  '.rtrim(implode('  ', $cells));
    }

    private function icon(string $kind): string
    {
        if (! $this->decorated) {
            return match ($kind) {
                'ok' => '+',
                'fail' => 'x',
                default => '!',
            };
        }

        return match ($kind) {
            'ok' => "\u{2713}",
            'fail' => "\u{2717}",
            default => '!',
        };
    }

    /**
     * @param resource $stream
     */
    private static function supportsAnsi($stream): bool
    {
        if (getenv('NO_COLOR') !== false) {
            return false;
        }

        if (! function_exists('stream_isatty') || ! @stream_isatty($stream)) {
            return false;
        }

        $term = getenv('TERM');

        return $term !== false && $term !== 'dumb';
    }

    /**
     * @return resource
     */
    private static function openStream(string $target)
    {
        $handle = fopen($target, 'wb');

        if ($handle === false) {
            throw new \RuntimeException("Unable to open stream {$target}");
        }

        return $handle;
    }
}