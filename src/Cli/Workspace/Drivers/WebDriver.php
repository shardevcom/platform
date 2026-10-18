<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace\Drivers;

use Shardevcom\Cli\Workspace\Component;
use Shardevcom\Cli\Workspace\ComponentType;
use Shardevcom\Cli\Workspace\Requirement;

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

    public function build(Component $component, string $path, ?string $guard = null): void
    {
        if ($guard === null) {
            $this->run('npm run build', $path);

            return;
        }

        $this->run('npm run build:'.self::app($guard), $path);
    }

    public function dev(Component $component, string $path, ?string $guard = null): void
    {
        $this->run('npm run dev:'.self::app($guard ?? $component->primaryGuard()), $path);
    }

    protected function verifyCommand(Component $component): string
    {
        return 'npm test';
    }

    /**
     * The `web` guard is served by the `user` app; every other guard maps 1:1.
     */
    private static function app(?string $guard): string
    {
        return $guard === null || $guard === 'web' ? 'user' : $guard;
    }

    public function requirements(): array
    {
        return [
            new Requirement(
                'Node.js',
                'node --version',
                'v',
                'Install Node.js 20+ and put it on PATH: https://nodejs.org',
                ['/usr/local/bin/node', '/usr/bin/node']
            ),
            new Requirement(
                'npm',
                'npm --version',
                '',
                'npm ships with Node.js; install Node.js 20+: https://nodejs.org',
                ['/usr/local/bin/npm', '/usr/bin/npm']
            ),
        ];
    }
}
