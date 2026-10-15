<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor\Rules;

use Shardevcom\Cli\Doctor\SecurityFinding;
use Shardevcom\Cli\Doctor\SecurityRule;
use Shardevcom\Cli\Doctor\SecuritySeverity;
use Shardevcom\Cli\Filesystem\Filesystem;
use Shardevcom\Cli\Process\ProcessRunner;

/**
 * Checks for API security issues.
 */
final class ApiSecurityRule implements SecurityRule
{
    public function id(): string
    {
        return 'api.security';
    }

    public function title(): string
    {
        return 'API endpoints should be secure';
    }

    public function category(): string
    {
        return 'api';
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

        // Check for GraphQL introspection in production
        $lighthouseConfig = $projectRoot.'/config/lighthouse.php';
        if ($filesystem->isFile($projectRoot.'/config/lighthouse.php')) {
            $config = $filesystem->read($projectRoot.'/config/lighthouse.php');

            if (str_contains($config, 'introspection') && !preg_match('/introspection.*false/', $config)) {
                $findings[] = SecurityFinding::insecureConfig(
                    ruleId: 'api.graphql-introspection',
                    title: 'GraphQL introspection enabled in production',
                    category: 'api',
                    severity: SecuritySeverity::Medium,
                    evidence: 'GraphQL introspection appears to be enabled',
                    description: 'GraphQL introspection allows clients to discover the full schema, which can leak API structure.',
                    recommendation: 'Disable introspection in production by setting introspection to false in config/lighthouse.php',
                    file: 'config/lighthouse.php',
                    confidence: 'medium',
                );
            }
        }

        // Check for API routes without authentication
        $routeFiles = $this->findRouteFiles($filesystem, $projectRoot);

        foreach ($routeFiles as $file) {
            $content = $filesystem->read($file);
            $relativePath = $this->getRelativePath($projectRoot, $file);

            // Check for API routes without auth middleware
            if (preg_match('/Route::(get|post|put|patch|delete|apiResource)\(.*\)/i', $content)) {
                $routes = $this->extractRoutes($content);

                foreach ($routes as $route) {
                    if (!$this->hasAuthMiddleware($route)) {
                        $findings[] = SecurityFinding::potentialRisk(
                            ruleId: 'api.unauthenticated-route',
                            title: 'API route without authentication middleware',
                            category: 'api',
                            severity: SecuritySeverity::High,
                            evidence: 'Route defined without auth middleware',
                            description: 'API route appears to lack authentication middleware, potentially exposing data.',
                            recommendation: 'Add auth:api or appropriate middleware to protect API endpoints.',
                            file: $this->getRelativePath($projectRoot, $file),
                            confidence: 'medium',
                        );
                    }
                }
            }

            // Check for GraphQL endpoint without rate limiting
            if (str_contains($content, 'graphql') || str_contains($content, 'GraphQL')) {
                if (!preg_match('/rate.?limit|throttle/i', $content)) {
                    $findings[] = SecurityFinding::insecureConfig(
                        ruleId: 'api.graphql-no-rate-limit',
                        title: 'GraphQL endpoint may lack rate limiting',
                        category: 'api',
                        severity: SecuritySeverity::Medium,
                        evidence: 'GraphQL endpoint detected without apparent rate limiting',
                        description: 'GraphQL endpoints without rate limiting are vulnerable to DoS attacks via complex queries.',
                        recommendation: 'Add rate limiting middleware to GraphQL endpoint.',
                        file: $this->getRelativePath($projectRoot, $file),
                        confidence: 'medium',
                    );
                }
            }
        }

        // Check for API versioning
        $apiRoutes = $this->findApiRoutes($filesystem, $projectRoot);
        $hasVersioning = false;
        foreach ($apiRoutes as $route) {
            if (preg_match('/v\d+\//', $route) || preg_match('/version.*=>/', $route)) {
                $hasVersioning = true;
                break;
            }
        }

        if (!$hasVersioning && !empty($apiRoutes)) {
            $findings[] = SecurityFinding::insecureConfig(
                ruleId: 'api.versioning-missing',
                title: 'API routes lack versioning',
                category: 'api',
                severity: SecuritySeverity::Medium,
                evidence: 'API routes do not appear to use versioning',
                description: 'API endpoints lack versioning, which makes backward-compatible changes difficult.',
                recommendation: 'Implement API versioning (e.g., /api/v1/, /api/v2/) for all endpoints.',
                file: 'routes/api.php',
                confidence: 'medium',
            );
        }

        // Check for OpenAPI/Swagger documentation
        $hasOpenApi = $filesystem->isFile($projectRoot.'/storage/api-docs/api-docs.json') ||
                     $filesystem->isFile($projectRoot.'/public/api-docs.json') ||
                     $filesystem->isFile($projectRoot.'/storage/app/api-docs/api-docs.json');

        if (!$hasOpenApi) {
            $findings[] = SecurityFinding::preventiveAdvice(
                ruleId: 'api.openapi-missing',
                title: 'No OpenAPI/Swagger documentation found',
                category: 'api',
                severity: SecuritySeverity::Low,
                evidence: 'No OpenAPI/Swagger documentation found',
                description: 'API documentation helps developers understand and securely integrate with the API.',
                recommendation: 'Generate OpenAPI documentation using a package like darkaonline/l5-swagger.',
                file: 'config/lighthouse.php',
                confidence: 'low',
            );
        }

        if ($findings === []) {
            return [
                SecurityFinding::preventiveAdvice(
                    ruleId: 'api.security.ok',
                    title: 'No obvious API security issues detected',
                    category: 'api',
                    severity: SecuritySeverity::Info,
                    evidence: 'No obvious API security issues detected',
                    description: 'API endpoints appear to have basic security measures.',
                    recommendation: 'Continue implementing security best practices for API endpoints.',
                    confidence: 'low',
                ),
            ];
        }

        return $findings;
    }

    /**
     * @return list<string>
     */
    private function findRouteFiles(Filesystem $filesystem, string $root): array
    {
        $files = [];
        $dirs = [
            $root.'/routes',
            $root.'/packages/*/routes',
        ];

        foreach ($dirs as $dir) {
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
            if (preg_match('/\.php$/i', $file)) {
                $files[] = $file;
            }
        }

        foreach ($filesystem->directories($dir) as $subDir) {
            $this->collectRouteFiles($filesystem, $subDir, $files);
        }
    }

    private function extractRoutes(string $content): array
    {
        $routes = [];
        preg_match_all('/Route::(get|post|put|patch|delete|apiResource|resource)\(([^)]+)\)/i', $content, $matches);

        for ($i = 0; $i < count($matches[0]); $i++) {
            $routes[] = $matches[0][$i];
        }

        return $routes;
    }

    private function hasAuthMiddleware(string $route): bool
    {
        return preg_match('/->middleware\(.*auth.*\)/', $route) ||
               preg_match('/middleware\(.*auth.*\)/', $route) ||
               preg_match('/->middleware\(.*auth:/', $route);
    }

    /**
     * @return list<string>
     */
    private function findApiRoutes(Filesystem $filesystem, string $root): array
    {
        $routes = [];
        $routeFiles = $this->findRouteFiles($filesystem, $root);

        foreach ($routeFiles as $file) {
            $content = $filesystem->read($file);
            preg_match_all('/Route::(get|post|put|patch|delete|apiResource)\([^)]+\)/i', $content, $matches);
            $routes = array_merge($routes, $matches[0] ?? []);
        }

        return $routes;
    }

    private function getRelativePath(string $root, string $file): string
    {
        return ltrim(str_replace($root, '', $file), '/');
    }
}