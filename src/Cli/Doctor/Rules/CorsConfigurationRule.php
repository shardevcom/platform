<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor\Rules;

use Shardevcom\Cli\Doctor\SecurityFinding;
use Shardevcom\Cli\Doctor\SecurityRule;
use Shardevcom\Cli\Doctor\SecuritySeverity;
use Shardevcom\Cli\Filesystem\Filesystem;
use Shardevcom\Cli\Process\ProcessRunner;

/**
 * Checks for secure CORS configuration.
 */
final class CorsConfigurationRule implements SecurityRule
{
    public function id(): string
    {
        return 'config.cors-configuration';
    }

    public function title(): string
    {
        return 'CORS should be properly configured';
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
        $findings = [];
        $configPath = $projectRoot.'/config/cors.php';

        if (!$filesystem->isFile($configPath)) {
            return [
                SecurityFinding::potentialRisk(
                    ruleId: $this->id().'.missing-config',
                    title: 'CORS configuration file not found',
                    category: $this->category(),
                    severity: SecuritySeverity::Medium,
                    evidence: 'config/cors.php not found',
                    description: 'CORS configuration file could not be located.',
                    recommendation: 'Ensure config/cors.php exists and is properly configured.',
                    file: 'config/cors.php',
                    confidence: 'medium',
                ),
            ];
        }

        $config = $filesystem->read($configPath);
        $findings = [];

        // Check for wildcard origins
        if (preg_match('/\'paths\'\s*=>\s*\[.*[\'"]\*[\'"]/', $config) ||
            preg_match('/\'allowed_origins\'\s*=>\s*\[.*[\'"]\*[\'"]/', $config) ||
            preg_match('/\'origins\'\s*=>\s*[\'"]\*[\'"]/', $config)) {
            $findings[] = SecurityFinding::insecureConfig(
                ruleId: $this->id().'.wildcard-origin',
                title: 'CORS allows wildcard origin (*)',
                category: $this->category(),
                severity: SecuritySeverity::Critical,
                evidence: 'CORS configuration allows wildcard origin (*)',
                description: 'CORS is configured to accept requests from any origin, which defeats the purpose of CORS protection.',
                recommendation: 'Specify explicit allowed origins in config/cors.php instead of using wildcard (*).',
                file: 'config/cors.php',
                confidence: 'high',
            );
        }

        // Check for credentials with wildcard
        if (preg_match('/\'supports_credentials\'\s*=>\s*true/', $config)) {
            if (preg_match('/\'allowed_origins\'\s*=>\s*\[.*[\'"]\*[\'"]/', $config) ||
                preg_match('/\'origins\'\s*=>\s*[\'"]\*[\'"]/', $config)) {
                $findings[] = SecurityFinding::confirmedVulnerability(
                    ruleId: $this->id().'.credentials-with-wildcard',
                    title: 'CORS allows credentials with wildcard origin',
                    category: $this->category(),
                    severity: SecuritySeverity::Critical,
                    evidence: 'supports_credentials=true with wildcard origin',
                    description: 'Allowing credentials with wildcard origin is a critical security vulnerability that enables credential theft.',
                    recommendation: 'Never use wildcard origin with supports_credentials=true. Specify explicit origins.',
                    file: 'config/cors.php',
                    confidence: 'high',
                );
            }
        }

        // Check for overly permissive methods
        if (preg_match('/\'allowed_methods\'\s*=>\s*\[.*[\'"]\*[\'"]/', $config)) {
            $findings[] = SecurityFinding::insecureConfig(
                ruleId: $this->id().'.wildcard-methods',
                title: 'CORS allows all HTTP methods',
                category: $this->category(),
                severity: SecuritySeverity::Medium,
                evidence: 'CORS allows all HTTP methods via wildcard',
                description: 'Allowing all HTTP methods via CORS may expose unintended endpoints.',
                recommendation: 'Specify only required HTTP methods (GET, POST, PUT, DELETE, etc.) in config/cors.php.',
                file: 'config/cors.php',
                confidence: 'medium',
            );
        }

        // Check for overly permissive headers
        if (preg_match('/\'allowed_headers\'\s*=>\s*\[.*[\'"]\*[\'"]/', $config)) {
            $findings[] = SecurityFinding::insecureConfig(
                ruleId: $this->id().'.wildcard-headers',
                title: 'CORS allows all headers',
                category: $this->category(),
                severity: SecuritySeverity::Medium,
                evidence: 'CORS allows all headers via wildcard',
                description: 'Allowing all headers via CORS may expose sensitive headers.',
                recommendation: 'Specify only required headers in config/cors.php.',
                file: 'config/cors.php',
                confidence: 'medium',
            );
        }

        if ($findings === []) {
            return [
                SecurityFinding::preventiveAdvice(
                    ruleId: $this->id().'.ok',
                    title: 'CORS properly configured',
                    category: $this->category(),
                    severity: SecuritySeverity::Info,
                    evidence: 'CORS configuration appears secure',
                    description: 'CORS configuration does not have obvious security issues.',
                    recommendation: 'Maintain current restrictive CORS configuration.',
                    confidence: 'high',
                ),
            ];
        }

        return $findings;
    }
}