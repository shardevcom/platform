<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Scaffold;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Exception\CommandFailed;

/**
 * Boundary: copies the reference desktop client shipped by the framework.
 *
 * The reference client is the demo's JavaFX app (demo/desktop), a Gradle
 * multi-module project with one app per guard (web/admin/employee) and plugins
 * per guard; it is copied as-is (never symlinked) so the project owns and can
 * edit it. Build output and the Gradle cache are excluded, but the wrapper is
 * kept so a fresh project can build without a system-wide Gradle.
 */
final class DesktopClientInstaller
{
    public const DIRECTORY = 'desktop-client';

    /** @var list<string> */
    private const EXCLUDE = [
        'build',
        '.gradle',
        '.git',
        '.idea',
        'out',
    ];

    public function __construct(private readonly Context $context) {}

    public function source(): string
    {
        return $this->context->frameworkPath('demo/desktop');
    }

    public function available(): bool
    {
        return $this->context->filesystem->isDirectory($this->source());
    }

    /**
     * @return array{path: string, installed: bool, skipped: bool, dependencies: bool}
     */
    public function install(?string $projectRoot = null, bool $installDependencies = true, bool $allowSkip = false): array
    {
        $projectRoot ??= $this->context->projectRoot();

        if (! $this->available()) {
            throw CommandFailed::create('The desktop client template is not available in this framework.');
        }

        $target = rtrim($projectRoot, '/').'/'.self::DIRECTORY;

        if ($this->context->filesystem->exists($target)) {
            if ($allowSkip) {
                return ['path' => $target, 'installed' => false, 'skipped' => true, 'dependencies' => false];
            }

            throw CommandFailed::create("The desktop client is already installed at {$target}. Remove it first.");
        }

        $this->context->filesystem->copyDirectory($this->source(), $target, self::EXCLUDE);

        $dependencies = false;

        if ($installDependencies) {
            $this->context->process->run('./gradlew build -x test', $target);
            $dependencies = true;
        }

        return ['path' => $target, 'installed' => true, 'skipped' => false, 'dependencies' => $dependencies];
    }
}
