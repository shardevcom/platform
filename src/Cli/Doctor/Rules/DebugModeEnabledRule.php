<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor\Rules;

use Shardevcom\Cli\Doctor\CheckResult;
use Shardevcom\Cli\Doctor\SecurityFinding;
use Shardevcom\Cli\Doctor\SecurityRule;
use Shardevcom\Cli\Doctor\SecuritySeverity;
use Shardevcom\Cli\Filesystem\Filesystem;
use Shardevcom\Cli\Process\ProcessRunner;

/**
 * Checks for dangerous debug configurations in production.
 */
final class DebugModeEnabledRule implements SecurityRule
{
    public function id(): string
    {
        return 'config.debug-enabled';
    }

    public function title(): string
    {
        return 'Debug mode should be disabled in production';
    }

    public function category(): string
    {
        return 'config';
    }

    public function defaultSeverity(): SecuritySeverity
    {
        return SecuritySeverity::High;
    }

    public function check(
        \Shardevcom\Cli\Filesystem\Filesystem $filesystem,
        \Shardevcom\Cli\Process\ProcessRunner $process,
        string $projectRoot
    ): array {
        $envPath = $projectRoot.'/.env';
        $envExamplePath = $projectRoot.'/.env.example';

        $findings = [];

        // Check .env
        if ($filesystem->isFile($envPath)) {
            $env = $filesystem->read($envPath);

            if (str_contains($env, 'APP_DEBUG=true') || preg_match('/APP_DEBUG\s*=\s*true/i', $env)) {
                $findings[] = SecurityFinding::insecureConfig(
                    ruleId: $this->id(),
                    title: $this->title(),
                    category: $this->category(),
                    severity: $this->defaultSeverity(),
                    evidence: 'APP_DEBUG=true found in .env',
                    description: 'Debug mode is enabled in production environment. This exposes stack traces, environment variables, and sensitive application details.',
                    recommendation: 'Set APP_DEBUG=false in .env for production environments.',
                    file: '.env',
                );
            }

            // Check for APP_ENV=production with APP_DEBUG=true
            if (preg_match('/APP_ENV\s*=\s*production/i', $env) && preg_match('/APP_DEBUG\s*=\s*true/i', $env)) {
                $findings[] = SecurityFinding::confirmedVulnerability(
                    ruleId: $this->id().'.env-mismatch',
                    title: 'APP_ENV=production with APP_DEBUG=true',
                    category: $this->category(),
                    severity: SecuritySeverity::Critical,
                    evidence: 'APP_ENV=production and APP_DEBUG=true both set',
                    description: 'Application is configured for production but debug mode is enabled, exposing sensitive information.',
                    recommendation: 'Set APP_DEBUG=false when APP_ENV=production.',
                    file: '.env',
                    confidence: 'high',
                );
            }
        }

        // Check .env.example
        if ($filesystem->isFile($envExamplePath)) {
            $envExample = $filesystem->read($envExamplePath);

            if (str_contains($envExample, 'APP_DEBUG=true') || preg_match('/APP_DEBUG\s*=\s*true/i', $envExample)) {
                $findings[] = SecurityFinding::preventiveAdvice(
                    ruleId: $this->id().'.env-example',
                    title: 'Debug mode enabled in .env.example',
                    category: $this->category(),
                    severity: SecuritySeverity::Low,
                    evidence: 'APP_DEBUG=true found in .env.example',
                    description: 'The example environment file has debug mode enabled, which could be copied to production.',
                    recommendation: 'Set APP_DEBUG=false in .env.example.',
                    file: '.env.example',
                    confidence: 'medium',
                );
            }
        }

        if ($findings === []) {
            return [
                SecurityFinding::preventiveAdvice(
                    ruleId: $this->id().'.ok',
                    title: 'Debug mode properly disabled',
                    category: $this->category(),
                    severity: SecuritySeverity::Info,
                    evidence: 'APP_DEBUG is not set to true in .env or .env.example',
                    description: 'Debug mode appears to be properly disabled.',
                    recommendation: 'Ensure APP_DEBUG=false remains set in production environments.',
                    confidence: 'high',
                ),
            ];
        }

        return $findings;
    }
}