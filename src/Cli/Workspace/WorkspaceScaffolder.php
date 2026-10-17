<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Exception\CommandFailed;
use Shardevcom\Cli\Scaffold\ProjectScaffolder;
use Shardevcom\Framework;

/**
 * Boundary: turns a component list into a working workspace on disk.
 *
 * `create` builds the workspace root (platform.json + one directory per
 * component) and `add` extends an existing workspace. The API is delegated to
 * ProjectScaffolder (composer create-project + platform packages); each client
 * is copied from the framework's reference template and its dependencies
 * installed by the matching driver.
 */
final class WorkspaceScaffolder
{
    public function __construct(private readonly Context $context) {}

    /**
     * @param list<Component> $components
     */
    public function create(
        string $target,
        array $components,
        string $laravelConstraint,
        bool $noInteraction,
        bool $installDependencies,
    ): WorkspaceManifest {
        $this->assertTargetIsFree($target);
        $this->context->filesystem->createDirectory($target);

        $manifest = WorkspaceManifest::make(
            basename(rtrim($target, '/')),
            Framework::version(),
            $components
        );
        $manifest->save($this->context->filesystem, $target);

        foreach ($components as $component) {
            if (! $component->enabled) {
                continue;
            }

            $this->createComponent($target, $component, $laravelConstraint, $noInteraction, $installDependencies);
        }

        return $manifest;
    }

    public function add(
        Workspace $workspace,
        Component $component,
        string $laravelConstraint,
        bool $noInteraction,
        bool $installDependencies,
    ): void {
        if ($workspace->manifest->has($component->name)) {
            throw CommandFailed::create("Component '{$component->name}' is already part of the workspace.");
        }

        $this->createComponent($workspace->root, $component, $laravelConstraint, $noInteraction, $installDependencies);
        $workspace->manifest->with($component)->save($this->context->filesystem, $workspace->root);
    }

    private function createComponent(
        string $root,
        Component $component,
        string $laravelConstraint,
        bool $noInteraction,
        bool $installDependencies,
    ): void {
        $path = rtrim($root, '/').'/'.$component->path;

        if ($component->type === ComponentType::Api) {
            $this->createApi($path, $laravelConstraint, $noInteraction);

            return;
        }

        $this->copyTemplate($component, $path);

        if ($installDependencies) {
            (new DriverRegistry($this->context))->driver($component->type)->install($component, $path);
        }
    }

    private function createApi(string $path, string $laravelConstraint, bool $noInteraction): void
    {
        $scaffolder = new ProjectScaffolder($this->context);

        $scaffolder->createLaravelProject($path, $laravelConstraint, $noInteraction);
        $scaffolder->installPackages($path);
        $scaffolder->configureComposer($path);
        $scaffolder->installDependencies($path);
        $scaffolder->registerProviders($path);
        $scaffolder->writePlatformConfig($path);
        $scaffolder->prepareEnvironment($path, Framework::version());
        $scaffolder->generateKey($path);
        $scaffolder->publishPassportMigrations($path);
        $scaffolder->generatePassportKeys($path);
        $scaffolder->migrate($path);
        $scaffolder->createPersonalAccessClients($path);
        $scaffolder->seed($path);
        $scaffolder->dumpAutoload($path);
        $scaffolder->verify($path, [
            ['php artisan route:list --path=api/v1', 'api/v1', 'REST routes are missing.'],
            ['php artisan route:list --path=graphql', 'graphql', 'The GraphQL route is missing.'],
            ['php artisan config:show platform', 'platform', 'config/platform.php could not be read.'],
            ['php artisan --version', 'Laravel Framework', 'The generated application does not boot.'],
        ]);
    }

    private function copyTemplate(Component $component, string $path): void
    {
        $source = $this->context->frameworkPath($component->type->template());

        if (! $this->context->filesystem->isDirectory($source)) {
            throw CommandFailed::create("The {$component->name} template is not available in this framework.");
        }

        $this->context->filesystem->copyDirectory($source, $path, $component->type->exclude());
    }

    private function assertTargetIsFree(string $target): void
    {
        if ($this->context->filesystem->isDirectory($target)) {
            throw CommandFailed::create("Directory '{$target}' already exists.");
        }
    }
}
