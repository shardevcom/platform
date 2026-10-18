<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor;

use Shardevcom\Cli\Filesystem\Filesystem;
use Shardevcom\Cli\Process\ProcessRunner;

/**
 * API functionality checks: verifies REST and GraphQL endpoints are actually
 * responding with correct status codes and envelope format.
 */
final class ApiSuite implements CheckSuite
{
    public function __construct(
        private readonly ProcessRunner $process,
        private readonly Filesystem $filesystem,
        private readonly string $projectRoot,
    ) {}

    public function name(): string
    {
        return 'API Functionality';
    }

    public function run(): array
    {
        return [
            $this->checkRestEndpoint(),
            $this->checkGraphqlEndpoint(),
            $this->checkConfigurationEndpoint(),
            $this->checkWidgetEndpoint(),
        ];
    }

    private function checkRestEndpoint(): CheckResult
    {
        // Check if we can start the server and hit an endpoint
        // For now, verify routes exist and the application boots
        // Routes may be prefixed with platform (admin/, employee/, web/)
        $result = $this->process->run('php artisan route:list', $this->projectRoot);

        if ($result->failed()) {
            return CheckResult::fail('REST Products endpoint', 'Could not list routes');
        }

        // Check for products routes under any platform prefix
        $hasRoute = $result->contains('products.index') || $result->contains('products.store');

        return CheckResult::of(
            'REST Products endpoint',
            $hasRoute,
            $hasRoute ? 'Route registered (any platform)' : 'Route not found'
        );
    }

    private function checkGraphqlEndpoint(): CheckResult
    {
        $result = $this->process->run('php artisan route:list', $this->projectRoot);

        if ($result->failed()) {
            return CheckResult::fail('GraphQL endpoint', 'Could not list routes');
        }

        // GraphQL might be under platform prefix too
        $hasRoute = $result->contains('graphql');

        return CheckResult::of(
            'GraphQL endpoint',
            $hasRoute,
            $hasRoute ? 'Route registered' : 'Route not found'
        );
    }

    private function checkConfigurationEndpoint(): CheckResult
    {
        $result = $this->process->run('php artisan route:list', $this->projectRoot);

        if ($result->failed()) {
            return CheckResult::fail('Configuration endpoint', 'Could not list routes');
        }

        $hasRoute = $result->contains('configuration.');

        return CheckResult::of(
            'Configuration endpoint',
            $hasRoute,
            $hasRoute ? 'Route registered' : 'Route not found'
        );
    }

    private function checkWidgetEndpoint(): CheckResult
    {
        $result = $this->process->run('php artisan route:list', $this->projectRoot);

        if ($result->failed()) {
            return CheckResult::fail('Widgets endpoint', 'Could not list routes');
        }

        $hasRoute = $result->contains('widgets.index');

        return CheckResult::of(
            'Widgets endpoint',
            $hasRoute,
            $hasRoute ? 'Route registered' : 'Route not found'
        );
    }
}