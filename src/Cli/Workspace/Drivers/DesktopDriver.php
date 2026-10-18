<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace\Drivers;

use Shardevcom\Cli\Workspace\Component;
use Shardevcom\Cli\Workspace\ComponentType;
use Shardevcom\Cli\Workspace\Requirement;

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

    public function build(Component $component, string $path, ?string $guard = null): void
    {
        if ($guard === null) {
            $this->run('./gradlew build', $path);

            return;
        }

        $this->run('./gradlew :apps:'.self::app($guard).':build', $path);
    }

    public function dev(Component $component, string $path, ?string $guard = null): void
    {
        $this->run('./gradlew :apps:'.self::app($guard ?? $component->primaryGuard()).':run', $path);
    }

    protected function verifyCommand(Component $component): string
    {
        return './gradlew test';
    }

    public function requirements(): array
    {
        return [
            new Requirement(
                'JDK',
                'java -version',
                'version',
                'Install JDK 21+ and set JAVA_HOME: https://adoptium.net (gradlew downloads Gradle itself)'
            ),
        ];
    }

    /**
     * The web guard is served by the `user` app; every other guard maps 1:1.
     */
    private static function app(?string $guard): string
    {
        return $guard === null || $guard === 'web' ? 'user' : $guard;
    }
}
