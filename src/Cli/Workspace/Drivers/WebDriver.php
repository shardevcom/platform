<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace\Drivers;

use Shardevcom\Cli\Workspace\Component;
use Shardevcom\Cli\Workspace\ComponentType;

/**
 * The web client component (Vite/React monorepo, one app per guard).
 */
final class WebDriver extends AbstractDriver
{
    public function type(): ComponentType
    {
        return ComponentType::Web;
    }

    public function install(Component $component, string $path): void
    {
        $this->run('npm install', $path);
    }

    public function build(Component $component, string $path): void
    {
        $this->run('npm run build', $path);
    }

    public function dev(Component $component, string $path): void
    {
        $this->run('npm run dev:'.($component->guard ?? 'admin'), $path);
    }

    protected function verifyCommand(Component $component): string
    {
        return 'npm test';
    }
}
