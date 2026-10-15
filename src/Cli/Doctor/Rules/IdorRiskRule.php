<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor\Rules;

use Shardevcom\Cli\Doctor\SecurityFinding;
use Shardevcom\Cli\Doctor\SecurityRule;
use Shardevcom\Cli\Doctor\SecuritySeverity;
use Shardevcom\Cli\Filesystem\Filesystem;
use Shardevcom\Cli\Process\ProcessRunner;

/**
 * Checks for potential IDOR (Insecure Direct Object Reference) vulnerabilities.
 */
final class IdorRiskRule implements SecurityRule
{
    public function id(): string
    {
        return 'authorization.idor-risk';
    }

    public function title(): string
    {
        return 'Routes should not expose direct object references without authorization';
    }

    public function category(): string
    {
        return 'authorization';
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

        // Find route files
        $routeFiles = $this->findRouteFiles($filesystem, $projectRoot);

        foreach ($routeFiles as $file) {
            $content = $filesystem->read($file);
            $relativePath = $this->getRelativePath($projectRoot, $file);

            // Check for routes with {id} or {uuid} parameters that might be direct object references
            if (preg_match('/\{.*?(id|uuid|slug).*?\}/', $content)) {
                // Check if there's authorization middleware or policy
                if (!$this->hasAuthorizationCheck($content)) {
                    $findings[] = SecurityFinding::potentialRisk(
                        ruleId: 'authorization.idor-risk.route',
                        title: 'Route with direct object reference may lack authorization',
                        category: 'authorization',
                        severity: SecuritySeverity::High,
                        evidence: 'Route parameter with id/uuid/slug found without apparent authorization check',
                        description: 'Route uses direct object reference (id, uuid, slug) without apparent authorization middleware or policy check. This could allow IDOR attacks.',
                        recommendation: 'Ensure routes with object references use authorization middleware, policies, or gates to verify ownership.',
                        file: $this->getRelativePath($projectRoot, $file),
                        confidence: 'medium',
                    );
                }
            }

            // Check for direct model queries without authorization
            if (preg_match('/\w+::find\(.*request\(\)/', $content) ||
                preg_match('/\w+::findOrFail\(.*request\(\)/', $content) ||
                preg_match('/\w+::where.*id.*request\(\)/', $content)) {
                $findings[] = SecurityFinding::potentialRisk(
                    ruleId: 'authorization.idor-risk.direct-query',
                    title: 'Direct model query with request input',
                    category: 'authorization',
                    severity: SecuritySeverity::High,
                    evidence: 'Direct model query using request input without apparent authorization',
                    description: 'Code appears to query models directly using request input without authorization checks.',
                    recommendation: 'Use repositories or services that enforce authorization before querying.',
                    file: $this->getRelativePath($projectRoot, $file),
                    confidence: 'medium',
                );
            }
        }

        // Check for policies
        $policyFiles = $filesystem->directories($projectRoot.'/app/Policies');
        if (empty($policyFiles) && $filesystem->isDirectory($projectRoot.'/app/Policies')) {
            $policyFiles = $filesystem->files($projectRoot.'/app/Policies');
        }

        if (empty($policyFiles)) {
            $findings[] = SecurityFinding::preventiveAdvice(
                ruleId: 'authorization.policies-missing',
                title: 'No authorization policies found',
                category: 'authorization',
                severity: SecuritySeverity::Medium,
                evidence: 'No policy files found in app/Policies',
                description: 'No authorization policies found. Consider using Laravel policies for consistent authorization.',
                recommendation: 'Create policies for models and register them in AuthServiceProvider.',
                confidence: 'medium',
            );
        }

        if ($findings === []) {
            return [
                SecurityFinding::preventiveAdvice(
                    ruleId: 'authorization.idor-risk.ok',
                    title: 'No obvious IDOR risks detected',
                    category: 'authorization',
                    severity: SecuritySeverity::Info,
                    evidence: 'No obvious IDOR patterns detected',
                    description: 'No obvious IDOR patterns found in routes and controllers.',
                    recommendation: 'Continue using authorization middleware, policies, and gates.',
                    confidence: 'medium',
                ),
            ];
        }

        return $findings;
    }

    private function hasAuthorizationCheck(string $content): bool
    {
        // Check for common authorization patterns
        $patterns = [
            '/->can\(/',
            '/->cant\(/',
            '/Gate::allows\(/',
            '/Gate::denies\(/',
            '/\$this->authorize\(/',
            '/\$this->authorizeResource\(/',
            '/middleware\(.*auth.*\)/',
            '/middleware\(.*can:/',
            '/policy\(/',
            '/->can\(/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function findRouteFiles(Filesystem $filesystem, string $root): array
    {
        $files = [];
        $routeDirs = [
            $root.'/routes',
            $root.'/packages/*/routes',
        ];

        foreach ($routeDirs as $dir) {
            if ($filesystem->isDirectory($dir)) {
                $this->collectRouteFiles($filesystem, $dir, $files);
            }
        }

        return $files;
    }

    private function collectRouteFiles(Filesystem $filesystem, string $dir, array &$files): void
    {
        if (!$filesystem->isDirectory($dir)) {
            return;
        }

        foreach ($filesystem->files($dir) as $file) {
            if (preg_match('/\.(php)$/i', $file)) {
                $files[] = $file;
            }
        }

        foreach ($filesystem->directories($dir) as $subDir) {
            $this->collectRouteFiles($filesystem, $subDir, $files);
        }
    }

    private function getRelativePath(string $root, string $file): string
    {
        return ltrim(str_replace($root, '', $file), '/');
    }
}