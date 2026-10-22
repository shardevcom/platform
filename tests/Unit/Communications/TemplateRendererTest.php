<?php

declare(strict_types=1);

namespace Tests\Unit\Communications;

use PHPUnit\Framework\TestCase;
use Shardevcom\Communications\Application\TemplateRenderer;
use Shardevcom\Core\Exceptions\ValidationFailed;

final class TemplateRendererTest extends TestCase
{
    public function test_replaces_flat_and_nested_variables(): void
    {
        $renderer = new TemplateRenderer();

        $result = $renderer->render(
            'Hola {{ nombre }}, saldo {{ account.balance }} {{ missing }}.',
            ['nombre' => 'Ana', 'account' => ['balance' => 1500]],
        );

        $this->assertSame('Hola Ana, saldo 1500 .', $result);
    }

    public function test_escapes_html_values_when_requested(): void
    {
        $renderer = new TemplateRenderer();

        $result = $renderer->render(
            '<p>{{ name }}</p>',
            ['name' => '<script>alert(1)</script>'],
            true,
        );

        $this->assertSame('<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>', $result);
    }

    public function test_strict_mode_rejects_missing_variables(): void
    {
        $renderer = new TemplateRenderer(true);

        $this->expectException(ValidationFailed::class);

        $renderer->render('Hola {{ name }}', []);
    }

    public function test_renders_arrays_as_json_and_booleans_as_text(): void
    {
        $renderer = new TemplateRenderer();

        $result = $renderer->render('{{ items }} {{ active }}', [
            'items' => ['a', 'b'],
            'active' => true,
        ]);

        $this->assertSame('["a","b"] true', $result);
    }
}
