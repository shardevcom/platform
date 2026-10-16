<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Scaffold;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Exception\CommandFailed;

/**
 * Boundary: copies the reference SPA shipped by the framework into a project.
 *
 * The reference client is the demo's web app (demo/web), a plain Vite/React
 * app that consumes the platform API; it is copied as-is (never symlinked) so
 * the project owns and can edit it. Build artifacts are excluded so a fresh
 * project never inherits the demo's node_modules or dist.
 */
final class ClientInstaller
{
    public const DIRECTORY = 'client';

    /** @var list<string> */
    private const EXCLUDE = ['node_modules', 'dist', '.git', '.vite'];

    public function __construct(private readonly Context $context) {}

    public function source(): string
    {
        return $this->context->frameworkPath('demo/web');
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
            throw CommandFailed::create('The client template is not available in this framework.');
        }

        $target = rtrim($projectRoot, '/').'/'.self::DIRECTORY;

        if ($this->context->filesystem->exists($target)) {
            if ($allowSkip) {
                return ['path' => $target, 'installed' => false, 'skipped' => true, 'dependencies' => false];
            }

            throw CommandFailed::create("The client is already installed at {$target}. Remove it first.");
        }

        $this->context->filesystem->copyDirectory($this->source(), $target, self::EXCLUDE);

        $dependencies = false;

        if ($installDependencies) {
            $this->context->process->run('npm install', $target);
            $dependencies = true;
        }

        return ['path' => $target, 'installed' => true, 'skipped' => false, 'dependencies' => $dependencies];
    }
}
