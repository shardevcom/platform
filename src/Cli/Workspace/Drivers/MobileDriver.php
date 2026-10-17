<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace\Drivers;

use Shardevcom\Cli\Workspace\Component;
use Shardevcom\Cli\Workspace\ComponentType;
use Shardevcom\Cli\Workspace\Requirement;

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

    public function requirements(): array
    {
        $bins = [
            '~/flutter/bin',
            '~/development/flutter/bin',
            '/usr/local/flutter/bin',
            '/opt/flutter/bin',
            '/snap/bin',
        ];

        return [
            new Requirement(
                'Flutter SDK',
                'flutter --version',
                'Flutter',
                'Install the Flutter SDK and add it to PATH: https://docs.flutter.dev/get-started/install',
                array_map(static fn (string $bin): string => $bin.'/flutter', $bins)
            ),
            new Requirement(
                'Dart',
                'dart --version',
                'Dart',
                'Dart ships with Flutter; install the Flutter SDK: https://docs.flutter.dev/get-started/install',
                array_map(static fn (string $bin): string => $bin.'/dart', $bins)
            ),
        ];
    }
}
