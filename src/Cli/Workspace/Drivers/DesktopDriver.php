<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace\Drivers;

use Shardevcom\Cli\Workspace\Component;
use Shardevcom\Cli\Workspace\ComponentType;

/**
 * The desktop client component (Gradle/JavaFX monorepo, one app per guard).
 */
final class DesktopDriver extends AbstractDriver
{
    public function type(): ComponentType
    {
        return ComponentType::Desktop;
    }

    public function install(Component $component, string $path): void
    {
        $this->run('./gradlew build -x test', $path);
    }

    public function build(Component $component, string $path): void
    {
        $this->run('./gradlew build', $path);
    }

    public function dev(Component $component, string $path): void
    {
        $this->run('./gradlew :apps:'.self::app($component->guard).':run', $path);
    }

    protected function verifyCommand(Component $component): string
    {
        return './gradlew test';
    }

    /**
     * The web guard is served by the `user` app; every other guard maps 1:1.
     */
    private static function app(?string $guard): string
    {
        return $guard === null || $guard === 'web' ? 'user' : $guard;
    }
}
