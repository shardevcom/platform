<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Scaffold;

use Shardevcom\Cli\Exception\CommandFailed;
use Shardevcom\Cli\Filesystem\Filesystem;
use Shardevcom\Cli\Packages\PackageCatalog;

/**
 * Boundary: the only writer of a generated project's composer.json.
 *
 * Namespaces, path repositories and requirements are derived from the
 * discovered packages, so adding a package to packages/ is enough for
 * `platform new` to install it. No package name is hardcoded here.
 */
final class ComposerEditor
{
    /** @var array<string, string> */
    private const BASE_REQUIREMENTS = [
        'laravel/passport' => '^13.0',
        'laravel/socialite' => '^5.0',
        'nuwave/lighthouse' => '^6.0',
        'maatwebsite/excel' => '^4.0',
        'pragmarx/google2fa-laravel' => '^1.0',
    ];

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly PackageCatalog $catalog,
    ) {}

    /**
     * Wires every discovered package into the project's composer.json.
     */
    public function configure(string $projectPath): void
    {
        $file = $projectPath.'/composer.json';
        $composer = $this->filesystem->readJson($file)
            ?? throw CommandFailed::create("composer.json not found in {$projectPath}");

        $composer['autoload']['psr-4'] = array_merge(
            (array) ($composer['autoload']['psr-4'] ?? []),
            $this->catalog->psr4Map()
        );

        ksort($composer['autoload']['psr-4']);

        $composer['repositories'] = array_values(array_filter(
            (array) ($composer['repositories'] ?? []),
            static fn (mixed $repository): bool => ! is_array($repository) || ($repository['type'] ?? null) !== 'path'
        ));

        $composer['repositories'][] = [
            'type' => 'path',
            'url' => $this->catalog->repositoryGlob(),
            'options' => ['symlink' => true],
        ];

        $composer['require'] = array_merge(
            (array) ($composer['require'] ?? []),
            self::BASE_REQUIREMENTS,
            $this->catalog->requirements()
        );

        ksort($composer['require']);

        $composer['minimum-stability'] = 'dev';
        $composer['prefer-stable'] = true;

        $this->filesystem->writeJson($file, $composer);
    }
}