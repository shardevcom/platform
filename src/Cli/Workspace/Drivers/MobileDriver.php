<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace\Drivers;

use Shardevcom\Cli\Workspace\Component;
use Shardevcom\Cli\Workspace\ComponentType;

/**
 * The mobile client component (Flutter monorepo, one flavor per guard).
 */
final class MobileDriver extends AbstractDriver
{
    public function type(): ComponentType
    {
        return ComponentType::Mobile;
    }

    public function install(Component $component, string $path): void
    {
        $this->run('flutter pub get', $path);
    }

    public function build(Component $component, string $path): void
    {
        $this->run('flutter build apk --debug', $path);
    }

    public function dev(Component $component, string $path): void
    {
        $this->run('flutter run --dart-define=GUARD='.($component->guard ?? 'web'), $path);
    }

    protected function verifyCommand(Component $component): string
    {
        return './tool/test.sh';
    }
}
