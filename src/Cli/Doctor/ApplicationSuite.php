<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor;

use Shardevcom\Cli\Process\ProcessRunner;

/**
 * Running-application checks: the REST and GraphQL surfaces must be routable
 * in the inspected project.
 */
final class ApplicationSuite implements CheckSuite
{
    public function __construct(
        private readonly ProcessRunner $process,
        private readonly string $projectRoot,
    ) {}

    public function name(): string
    {
        return 'Running application';
    }

    public function run(): array
    {
        return [
            $this->routeRegistered('REST routes', 'api/v1'),
            $this->routeRegistered('GraphQL route', 'graphql'),
        ];
    }

    private function routeRegistered(string $label, string $path): CheckResult
    {
        $result = $this->process->run('php artisan route:list --path='.$path, $this->projectRoot);

        if ($result->failed()) {
            return CheckResult::fail($label, 'artisan could not list routes');
        }

        return CheckResult::of(
            $label,
            $result->contains($path),
            $result->contains($path) ? 'Registered' : 'Not registered'
        );
    }
}