<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor;

/**
 * Boundary: the result of one health check (label, verdict, detail).
 *
 * A plain value object so checks stay independent of the printer and the
 * doctor output can be asserted in tests without capturing a terminal.
 */
final class CheckResult
{
    private function __construct(
        public readonly string $label,
        public readonly bool $passed,
        public readonly string $detail,
    ) {}

    public static function pass(string $label, string $detail = ''): self
    {
        return new self($label, true, $detail);
    }

    public static function fail(string $label, string $detail = ''): self
    {
        return new self($label, false, $detail);
    }

    public static function of(string $label, bool $passed, string $detail = ''): self
    {
        return new self($label, $passed, $detail);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    public function toRow(): array
    {
        return [$this->passed ? 'PASS' : 'FAIL', $this->label, $this->detail];
    }
}