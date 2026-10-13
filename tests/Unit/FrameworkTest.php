<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shardevcom\Framework;

/**
 * Installer foundation: version identity + Laravel compatibility gate
 * that `platform new` (T-003) enforces before scaffolding.
 */
final class FrameworkTest extends TestCase
{
    public function test_version_is_defined(): void
    {
        $this->assertNotEmpty(Framework::version());
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function versions(): array
    {
        return [
            'major only' => ['12', true],
            'minor' => ['12.61', true],
            'caret' => ['^12.0', true],
            'x suffix' => ['12.x', true],
            'laravel 13 major' => ['13', true],
            'laravel 13 minor' => ['13.0', true],
            'laravel 13 caret' => ['^13.0', true],
            'laravel 13 x suffix' => ['13.x', true],
            'laravel 11 rejected' => ['11', false],
            'laravel 9 rejected' => ['9', false],
        ];
    }

    #[DataProvider('versions')]
    public function test_supports_laravel(string $requested, bool $expected): void
    {
        $this->assertSame($expected, Framework::supportsLaravel($requested));
    }

    public function test_incompatibility_message_names_supported_lines(): void
    {
        $message = Framework::incompatibilityMessage('9');

        $this->assertStringContainsString('9', $message);
        $this->assertStringContainsString('^12.0', $message);
        $this->assertStringContainsString('^13.0', $message);
    }
}
