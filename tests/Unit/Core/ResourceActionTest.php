<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Shardevcom\Core\ResourceAction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * T-012 acceptance: canonical actions, custom segments without editing
 * Core, strict segment validation.
 */
final class ResourceActionTest extends TestCase
{
    public function test_canonical_cases_hold_expected_values(): void
    {
        $this->assertSame('view', ResourceAction::VIEW->value);
        $this->assertSame('create', ResourceAction::CREATE->value);
        $this->assertSame('update', ResourceAction::UPDATE->value);
        $this->assertSame('delete', ResourceAction::DELETE->value);
        $this->assertSame('restore', ResourceAction::RESTORE->value);
        $this->assertSame(ResourceAction::VIEW, ResourceAction::from('view'));
    }

    public function test_permission_derivation_for_canonical_action(): void
    {
        $this->assertSame('products.view', ResourceAction::permission('products', ResourceAction::VIEW));
        $this->assertSame('products.delete', ResourceAction::permission('products', ResourceAction::DELETE));
    }

    public function test_custom_action_needs_no_core_change(): void
    {
        $this->assertSame('publish', ResourceAction::segment('publish'));
        $this->assertSame('products.publish', ResourceAction::permission('products', 'publish'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidSegments(): array
    {
        return [
            'empty' => [''],
            'uppercase' => ['Publish'],
            'with dot' => ['products.view'],
            'with space' => ['bulk publish'],
            'with slash' => ['a/b'],
        ];
    }

    #[DataProvider('invalidSegments')]
    public function test_invalid_custom_segments_are_rejected(string $segment): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ResourceAction::segment($segment);
    }
}
