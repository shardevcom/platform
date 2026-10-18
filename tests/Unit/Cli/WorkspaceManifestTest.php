<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use Shardevcom\Cli\Workspace\Component;
use Shardevcom\Cli\Workspace\ComponentType;
use Shardevcom\Cli\Workspace\Workspace;
use Shardevcom\Cli\Workspace\WorkspaceManifest;
use Tests\Unit\Cli\Support\FakeFilesystem;

/**
 * Acceptance for the workspace manifest (platform.json) and its discovery.
 *
 * The manifest is the source of truth every workspace command reads, so it must
 * round-trip components faithfully and be discoverable from inside any member.
 */
final class WorkspaceManifestTest extends TestCase
{
    public function test_round_trips_components_through_platform_json(): void
    {
        $filesystem = new FakeFilesystem();
        $manifest = WorkspaceManifest::make('demo', '0.1.0', [
            Component::create('api'),
            Component::create('web'),
        ]);
        $manifest->save($filesystem, '/ws');

        $loaded = WorkspaceManifest::load($filesystem, '/ws');

        $this->assertSame('demo', $loaded->name);
        $this->assertTrue($loaded->has('api'));
        $this->assertSame(ComponentType::Api, $loaded->component('api')->type);
        $this->assertSame(['web'], $loaded->component('web')->guards);
        $this->assertCount(2, $loaded->enabled());
    }

    public function test_round_trips_multiple_guards_per_component(): void
    {
        $filesystem = new FakeFilesystem();
        $manifest = WorkspaceManifest::make('demo', '0.1.0', [
            new Component('web', ComponentType::Web, 'web', ['web', 'admin', 'employee']),
        ]);
        $manifest->save($filesystem, '/ws');

        $loaded = WorkspaceManifest::load($filesystem, '/ws');

        $this->assertSame(['web', 'admin', 'employee'], $loaded->component('web')->guards);
        $this->assertSame(['web', 'admin', 'employee'], $loaded->guards());
        $this->assertSame('web', $loaded->component('web')->primaryGuard());
        $this->assertTrue($loaded->component('web')->serves('employee'));
    }

    public function test_loads_the_legacy_single_guard_key(): void
    {
        $filesystem = new FakeFilesystem([
            '/ws/platform.json' => '{"name":"demo","components":{"web":{"type":"web","path":"web","guard":"admin"}}}',
        ]);

        $loaded = WorkspaceManifest::load($filesystem, '/ws');

        $this->assertSame(['admin'], $loaded->component('web')->guards);
    }

    public function test_defaults_the_guards_per_component_type(): void
    {
        $this->assertSame([], Component::create('api')->guards);
        $this->assertSame(['web'], Component::create('web')->guards);
        $this->assertSame(['web'], Component::create('mobile')->guards);
        $this->assertSame(['web'], Component::create('desktop')->guards);
    }

    public function test_locates_the_workspace_from_inside_a_component(): void
    {
        $filesystem = new FakeFilesystem([
            '/ws/platform.json' => '{"name":"demo","components":{"api":{"type":"api","path":"api"}}}',
        ]);

        $workspace = Workspace::locate($filesystem, '/ws/api/app', '/framework');

        $this->assertInstanceOf(Workspace::class, $workspace);
        $this->assertSame('/ws', $workspace->root);
    }

    public function test_returns_null_outside_a_workspace(): void
    {
        $this->assertNull(Workspace::locate(new FakeFilesystem(), '/tmp/x', '/framework'));
    }
}
