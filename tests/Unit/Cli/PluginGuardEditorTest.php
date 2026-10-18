<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use Shardevcom\Cli\Plugins\PluginGuardEditor;

/**
 * The editor must add a guard without reformatting the pretty-printed manifest
 * (it is edited textually, like config/platforms.php).
 */
final class PluginGuardEditorTest extends TestCase
{
    private const MANIFEST = <<<'JSON'
        {
          "name": "product",
          "title": "Products",
          "resource": "products",
          "actions": ["view", "list"],
          "guards": {
            "web": { "mode": "read", "path": "products" }
          }
        }
        JSON;

    public function test_it_appends_the_guard_with_the_plugin_resource(): void
    {
        $edited = (new PluginGuardEditor())->add(self::MANIFEST, 'partner');

        $data = json_decode($edited, true);
        $this->assertSame('read', $data['guards']['partner']['mode']);
        $this->assertSame('products', $data['guards']['partner']['path']);
        $this->assertSame('read', $data['guards']['web']['mode']);
    }

    public function test_it_keeps_the_one_line_guard_layout(): void
    {
        $edited = (new PluginGuardEditor())->add(self::MANIFEST, 'partner');

        $this->assertStringContainsString('"partner": { "mode": "read", "path": "products" },', $edited);
        $this->assertStringContainsString('"web": { "mode": "read", "path": "products" }', $edited);
    }

    public function test_it_accepts_a_custom_mode(): void
    {
        $data = json_decode((new PluginGuardEditor())->add(self::MANIFEST, 'partner', 'full'), true);

        $this->assertSame('full', $data['guards']['partner']['mode']);
    }

    public function test_it_handles_an_empty_guard_map(): void
    {
        $empty = '{"name":"x","resource":"x","guards":{}}';

        $data = json_decode((new PluginGuardEditor())->add($empty, 'partner'), true);

        $this->assertSame('read', $data['guards']['partner']['mode']);
    }

    public function test_has_reports_existing_guards(): void
    {
        $editor = new PluginGuardEditor();

        $this->assertTrue($editor->has(self::MANIFEST, 'web'));
        $this->assertFalse($editor->has(self::MANIFEST, 'partner'));
    }
}
