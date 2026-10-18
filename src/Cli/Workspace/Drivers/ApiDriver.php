<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace\Drivers;

use Shardevcom\Cli\Workspace\Component;
use Shardevcom\Cli\Workspace\ComponentType;

/**
 * The Laravel API component (composer + artisan).
 */
final class ApiDriver extends AbstractDriver
{
    public function type(): ComponentType
    {
        return ComponentType::Api;
    }

    public function install(Component $component, string $path): void
    {
        $this->run('composer update --no-interaction -n', $path);
    }

    public function build(Component $component, string $path, ?string $guard = null): void
    {
        $this->run('composer dump-autoload -o', $path);
    }

    public function dev(Component $component, string $path, ?string $guard = null): void
    {
        $this->run('php artisan serve', $path);
    }

    protected function verifyCommand(Component $component): string
    {
        return 'php artisan --version';
    }
}
