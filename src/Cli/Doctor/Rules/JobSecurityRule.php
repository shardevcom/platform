<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor\Rules;

use Shardevcom\Cli\Doctor\SecurityFinding;
use Shardevcom\Cli\Doctor\SecurityRule;
use Shardevcom\Cli\Doctor\SecuritySeverity;
use Shardevcom\Cli\Filesystem\Filesystem;
use Shardevcom\Cli\Process\ProcessRunner;

/**
 * Checks for security issues in queued jobs.
 */
final class JobSecurityRule implements SecurityRule
{
    public function id(): string
    {
        return 'jobs.security';
    }

    public function title(): string
    {
        return 'Queued jobs should be secure and idempotent';
    }

    public function category(): string
    {
        return 'jobs';
    }

    public function defaultSeverity(): SecuritySeverity
    {
        return SecuritySeverity::Medium;
    }

    public function check(
        \Shardevcom\Cli\Filesystem\Filesystem $filesystem,
        \Shardevcom\Cli\Process\ProcessRunner $process,
        string $projectRoot
    ): array {
        $findings = [];

        // Find job files
        $jobFiles = $this->findJobFiles($filesystem, $projectRoot);

        foreach ($jobFiles as $file) {
            $content = $filesystem->read($file);
            $relativePath = $this->getRelativePath($projectRoot, $file);

            // Check for ShouldQueue without proper authorization
            if (str_contains($content, 'implements ShouldQueue') || str_contains($content, 'use Queueable')) {
                // Check if job uses user input without validation
                if (preg_match('/\$this->.*request\(\)/', $content) ||
                    preg_match('/\$request->/', $content)) {
                    $findings[] = SecurityFinding::potentialRisk(
                        ruleId: 'jobs.request-in-job',
                        title: 'Job uses request data directly',
                        category: 'jobs',
                        severity: SecuritySeverity::Medium,
                        evidence: 'Job uses $request directly',
                        description: 'Job accesses request data directly, which may not be available when the job executes asynchronously.',
                        recommendation: 'Pass required data as job constructor parameters instead of using request() helper.',
                        file: $this->getRelativePath($projectRoot, $file),
                        confidence: 'medium',
                    );
                }

                // Check for file operations with user input
                if (preg_match('/Storage::.*\$request->/', $content) ||
                    preg_match('/Storage::.*\$this->.*file/', $content) ||
                    preg_match('/Storage::.*\$this->.*path/', $content)) {
                    $findings[] = SecurityFinding::potentialRisk(
                        ruleId: 'jobs.file-operations',
                        title: 'Job performs file operations with potential user input',
                        category: 'jobs',
                        severity: SecuritySeverity::Medium,
                        evidence: 'Job performs file operations with user-controlled input',
                        description: 'Job performs file operations using user-controlled paths, which could lead to path traversal.',
                        recommendation: 'Validate and sanitize file paths. Use signed URLs or temporary signed paths for file access.',
                        file: $this->getRelativePath($projectRoot, $file),
                        confidence: 'medium',
                    );
                }

                // Check for missing idempotency
                if (preg_match('/DB::transaction\(/', $content) ||
                    preg_match('/DB::beginTransaction\(/', $content) ||
                    preg_match('/->save\(\)/', $content) ||
                    preg_match('/->create\(/', $content) ||
                    preg_match('/->update\(/', $content)) {
                    if (!preg_match('/idempotent|unique|firstOrCreate|updateOrCreate/', $content)) {
                        $findings[] = SecurityFinding::preventiveAdvice(
                            ruleId: 'jobs.idempotency',
                            title: 'Job performs non-idempotent operations',
                            category: 'jobs',
                            severity: SecuritySeverity::Low,
                            evidence: 'Job performs write operations without apparent idempotency protection',
                            description: 'Job performs database writes without apparent idempotency protection. Retries could cause duplicate operations.',
                            recommendation: 'Implement idempotency keys or use firstOrCreate/updateOrCreate for idempotent operations.',
                            file: $this->getRelativePath($projectRoot, $file),
                            confidence: 'low',
                        );
                    }
                }

                // Check for sensitive data in job payload
                if (preg_match('/password|secret|token|api_key|api_token|access_token|refresh_token/', $content)) {
                    $findings[] = SecurityFinding::potentialRisk(
                        ruleId: 'jobs.sensitive-data',
                        title: 'Job may contain sensitive data in payload',
                        category: 'jobs',
                        severity: SecuritySeverity::Medium,
                        evidence: 'Job class contains references to sensitive data',
                        description: 'Job may store sensitive data (passwords, tokens, keys) in the job payload which gets serialized and stored in the queue.',
                        recommendation: 'Avoid storing sensitive data in job payloads. Pass references or IDs instead.',
                        file: $this->getRelativePath($projectRoot, $file),
                        confidence: 'medium',
                    );
            }
        }

        // Check for jobs without proper failure handling
        $jobFiles = $this->findJobFiles($filesystem, $projectRoot);
        foreach ($jobFiles as $file) {
            $content = $filesystem->read($file);

            if (str_contains($content, 'implements ShouldQueue')) {
                if (!preg_match('/implements.*ShouldQueue/', $content)) {
                    // Already checked above
                }

                // Check for retry logic
                if (!preg_match('/\$tries\s*=', $content) &&
                    !preg_match('/\$retryAfter\s*=', $content) &&
                    !preg_match('/\$maxExceptions\s*=', $content) &&
                    !preg_match('/\$failOnTimeout\s*=/i', $content)) {
                    // This is just advisory
                }
            }
        }

        if ($findings === []) {
            return [
                SecurityFinding::preventiveAdvice(
                    ruleId: 'jobs.security.ok',
                    title: 'No obvious job security issues detected',
                    category: 'jobs',
                    severity: SecuritySeverity::Info,
                    evidence: 'No obvious security issues in job classes',
                    description: 'Job classes appear to follow basic security practices.',
                    recommendation: 'Continue following secure job practices.',
                    confidence: 'low',
                ),
            ];
        }

        return $findings;
        }

        return $findings;
    }

    /**
     * @return list<string>
     */
    private function findJobFiles(Filesystem $filesystem, string $root): array
    {
        $files = [];
        $dirs = [
            $root.'/app/Jobs',
            $root.'/packages/*/src/Jobs',
        ];

        foreach ($dirs as $dir) {
            $this->collectJobFiles($filesystem, $dir, $files);
        }

        return $files;
    }

    private function collectJobFiles(Filesystem $filesystem, string $dir, array &$files): void
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
            $this->collectJobFiles($filesystem, $subDir, $files);
        }
    }

    private function getRelativePath(string $root, string $file): string
    {
        return ltrim(str_replace($root, '', $file), '/');
    }
}