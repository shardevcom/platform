<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace;

/**
 * Boundary: how the CLI operates on one kind of component.
 *
 * Each client type (API, web, mobile, desktop) speaks a different toolchain, so
 * the workspace commands never branch on the type themselves: they look up the
 * driver and call install/build/dev/verify. Adding a component type means
 * adding a driver, not editing every command.
 */
interface ComponentDriver
{
    public function type(): ComponentType;

    public function install(Component $component, string $path): void;

    public function build(Component $component, string $path): void;

    public function dev(Component $component, string $path): void;

    public function verify(Component $component, string $path): bool;

    /**
     * External tools this component needs to build or run, probed by `doctor`.
     *
     * The API returns none: its PHP/Composer toolchain is already covered by
     * the doctor's Toolchain suite.
     *
     * @return list<Requirement>
     */
    public function requirements(): array;
}
