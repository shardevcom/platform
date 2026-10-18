<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor\Rules;

use Shardevcom\Cli\Doctor\SecurityFinding;
use Shardevcom\Cli\Doctor\SecurityRule;
use Shardevcom\Cli\Doctor\SecuritySeverity;
use Shardevcom\Cli\Filesystem\Filesystem;
use Shardevcom\Cli\Process\ProcessRunner;

/**
 * Checks for secure session cookie configuration.
 */
final class SessionCookieSecureRule implements SecurityRule
{
    public function id(): string
    {
        return 'config.session-cookie-secure';
    }

    public function title(): string
    {
        return 'Session cookie should have secure and httpOnly flags';
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
        $configPath = $projectRoot.'/config/session.php';

        if (!$filesystem->isFile($configPath)) {
            return [
                SecurityFinding::potentialRisk(
                    ruleId: $this->id().'.missing-config',
                    title: 'Session configuration file not found',
                    category: $this->category(),
                    severity: SecuritySeverity::Medium,
                    evidence: 'config/session.php not found',
                    description: 'Session configuration file could not be located.',
                    recommendation: 'Ensure config/session.php exists and is properly configured.',
                    file: 'config/session.php',
                    confidence: 'medium',
                ),
            ];
        }

        $config = $filesystem->read($configPath);

        $findings = [];

        // Check secure flag
        if (!preg_match('/\'secure\'\s*=>\s*true/', $config) && !preg_match('/\'secure\'\s*=>\s*env\(/', $config)) {
            $findings[] = SecurityFinding::insecureConfig(
                ruleId: $this->id().'.secure-flag',
                title: 'Session cookie secure flag not enabled',
                category: $this->category(),
                severity: SecuritySeverity::High,
                evidence: 'Session cookie secure flag is not set to true',
                description: 'Session cookies are transmitted without the Secure flag, making them vulnerable to interception over non-HTTPS connections.',
                recommendation: 'Set \'secure\' => true in config/session.php or ensure SESSION_SECURE_COOKIE=true in .env',
                file: 'config/session.php',
                confidence: 'high',
            );
        }

        // Check httpOnly flag
        if (!preg_match('/\'http_only\'\s*=>\s*true/', $config) && !preg_match('/\'http_only\'\s*=>\s*env\(/', $config)) {
            $findings[] = SecurityFinding::insecureConfig(
                ruleId: $this->id().'.httponly-flag',
                title: 'Session cookie httpOnly flag not enabled',
                category: $this->category(),
                severity: SecuritySeverity::High,
                evidence: 'Session cookie httpOnly flag is not set to true',
                description: 'Session cookies are accessible via JavaScript, increasing XSS risk.',
                recommendation: 'Set \'http_only\' => true in config/session.php',
                file: 'config/session.php',
                confidence: 'high',
            );
        }

        // Check same_site
        if (!preg_match('/\'same_site\'\s*=>\s*[\'"]lax[\'"]/i', $config) && !preg_match('/\'same_site\'\s*=>\s*[\'"]strict[\'"]/i', $config) && !preg_match('/\'same_site\'\s*=>\s*env\(/', $config)) {
            $findings[] = SecurityFinding::insecureConfig(
                ruleId: $this->id().'.samesite',
                title: 'Session cookie SameSite attribute not set to lax or strict',
                category: $this->category(),
                severity: SecuritySeverity::Medium,
                evidence: 'SameSite attribute is not set to lax or strict',
                description: 'Missing SameSite attribute increases CSRF risk.',
                recommendation: 'Set \'same_site\' => \'lax\' or \'strict\' in config/session.php',
                file: 'config/session.php',
                confidence: 'medium',
            );
        }

        // Check for secure cookie domain
        if (preg_match('/\'domain\'\s*=>\s*env\([\'"]SESSION_DOMAIN[\'"]\)/', $config)) {
            // This is fine - using env var
        } elseif (preg_match('/\'domain\'\s*=>\s*[\'"][^\'"]+[\'"]/', $config)) {
            // Hardcoded domain - might be an issue
        }

        if ($findings === []) {
            return [
                SecurityFinding::preventiveAdvice(
                    ruleId: $this->id().'.ok',
                    title: 'Session cookie properly configured',
                    category: $this->category(),
                    severity: SecuritySeverity::Info,
                    evidence: 'Session cookie has secure, httpOnly, and sameSite flags properly configured',
                    description: 'Session cookie appears to be properly secured.',
                    recommendation: 'Maintain current secure session cookie configuration.',
                    confidence: 'high',
                ),
            ];
        }

        return $findings;
    }
}