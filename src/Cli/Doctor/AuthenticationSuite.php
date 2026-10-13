<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor;

use Shardevcom\Cli\Filesystem\Filesystem;
use Shardevcom\Cli\Process\ProcessRunner;

/**
 * Authentication system checks: verifies Passport/OAuth, guards, and providers
 * are correctly configured and functional.
 */
final class AuthenticationSuite implements CheckSuite
{
    public function __construct(
        private readonly ProcessRunner $process,
        private readonly Filesystem $filesystem,
        private readonly string $projectRoot,
    ) {}

    public function name(): string
    {
        return 'Authentication';
    }

    public function run(): array
    {
        return [
            $this->checkPassportInstalled(),
            $this->checkPassportKeys(),
            $this->checkAuthGuards(),
            $this->checkAuthProviders(),
            $this->checkPasswordResetTables(),
            $this->checkPersonalAccessClients(),
        ];
    }

    private function checkPassportInstalled(): CheckResult
    {
        $composer = $this->filesystem->readJson($this->projectRoot.'/composer.json') ?? [];
        $require = array_merge($composer['require'] ?? [], $composer['require-dev'] ?? []);
        $hasPassport = isset($require['laravel/passport']);

        return CheckResult::of(
            'Passport installed',
            $hasPassport,
            $hasPassport ? 'laravel/passport required' : 'laravel/passport not found in composer.json'
        );
    }

    private function checkPassportKeys(): CheckResult
    {
        // Passport stores keys in storage/ as oauth-private.key and oauth-public.key
        // (not in a subdirectory)
        $privateKey = $this->projectRoot.'/storage/oauth-private.key';
        $publicKey = $this->projectRoot.'/storage/oauth-public.key';

        $hasPrivate = $this->filesystem->isFile($privateKey);
        $hasPublic = $this->filesystem->isFile($publicKey);

        return CheckResult::of(
            'Passport keys',
            $hasPrivate && $hasPublic,
            ($hasPrivate && $hasPublic) ? 'Keys present in storage/' : 'Missing keys (run: php artisan passport:keys)'
        );
    }

    private function checkAuthGuards(): CheckResult
    {
        $result = $this->process->run('php artisan config:show auth.guards', $this->projectRoot);

        if ($result->failed()) {
            return CheckResult::fail('Auth guards', 'Could not read auth.guards config');
        }

        $hasApiGuard = $result->contains('api') || $result->contains('admin') || $result->contains('employee');
        $hasWebGuard = $result->contains('web');

        return CheckResult::of(
            'Auth guards configured',
            $hasApiGuard && $hasWebGuard,
            ($hasApiGuard && $hasWebGuard) ? 'Multiple guards configured' : 'Missing expected guards'
        );
    }

    private function checkAuthProviders(): CheckResult
    {
        $result = $this->process->run('php artisan config:show auth.providers', $this->projectRoot);

        if ($result->failed()) {
            return CheckResult::fail('Auth providers', 'Could not read auth.providers config');
        }

        $hasWebProvider = $result->contains('users') || $result->contains('web');
        $hasAdminProvider = $result->contains('admin') || $result->contains('admins');
        $hasEmployeeProvider = $result->contains('employee') || $result->contains('employees');

        return CheckResult::of(
            'Auth providers configured',
            $hasWebProvider && $hasAdminProvider && $hasEmployeeProvider,
            ($hasWebProvider && $hasAdminProvider && $hasEmployeeProvider) ? 'Web, admin, employee providers found' : 'Missing expected providers'
        );
    }

    private function checkPasswordResetTables(): CheckResult
    {
        $result = $this->process->run('php artisan config:show auth.passwords', $this->projectRoot);

        if ($result->failed()) {
            return CheckResult::fail('Password reset tables', 'Could not read auth.passwords config');
        }

        $hasWeb = $result->contains('password_reset_tokens') || $result->contains('web');
        $hasAdmin = $result->contains('admin_password_resets');
        $hasEmployee = $result->contains('employee_password_resets');

        return CheckResult::of(
            'Password reset tables',
            $hasWeb && $hasAdmin && $hasEmployee,
            ($hasWeb && $hasAdmin && $hasEmployee) ? 'Web, admin, employee reset tables configured' : 'Missing reset table configuration'
        );
    }

    private function checkPersonalAccessClients(): CheckResult
    {
        // Check if personal access clients exist in the database for each platform
        // Note: newer Passport versions don't have personal_access_client column,
        // so we check by name pattern
        $result = $this->process->run('php artisan tinker --execute="
            \$clients = DB::table(\"oauth_clients\")->where(\"name\", \"like\", \"Shardev%personal access%\")->get();
            foreach (\$clients as \$client) {
                echo \$client->name . \" (provider: \" . \$client->provider . \")\" . PHP_EOL;
            }
        "', $this->projectRoot);

        if ($result->failed()) {
            return CheckResult::fail('Personal access clients', 'Could not query oauth_clients table');
        }

        $output = $result->output;
        $hasWeb = str_contains($output, 'web personal access');
        $hasAdmin = str_contains($output, 'admin personal access');
        $hasEmployee = str_contains($output, 'employee personal access');

        return CheckResult::of(
            'Personal access clients',
            $hasWeb && $hasAdmin && $hasEmployee,
            ($hasWeb && $hasAdmin && $hasEmployee) ? 'Web, admin, employee clients found' : 'Missing clients for one or more platforms'
        );
    }
}