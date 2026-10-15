<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor\Rules;

use Shardevcom\Cli\Doctor\SecurityFinding;
use Shardevcom\Cli\Doctor\SecurityRule;
use Shardevcom\Cli\Doctor\SecuritySeverity;
use Shardevcom\Cli\Filesystem\Filesystem;
use Shardevcom\Cli\Process\ProcessRunner;

/**
 * Checks for exposed secrets in codebase.
 */
final class ExposedSecretsRule implements SecurityRule
{
    public function id(): string
    {
        return 'code.exposed-secrets';
    }

    public function title(): string
    {
        return 'No hardcoded secrets in source code';
    }

    public function category(): string
    {
        return 'code';
    }

    public function defaultSeverity(): SecuritySeverity
    {
        return SecuritySeverity::Critical;
    }

    public function check(
        \Shardevcom\Cli\Filesystem\Filesystem $filesystem,
        \Shardevcom\Cli\Process\ProcessRunner $process,
        string $projectRoot
    ): array {
        $findings = [];

        // Patterns for common secrets
        $patterns = [
            'AWS Access Key' => '/AKIA[0-9A-Z]{16}/',
            'AWS Secret Key' => '/[A-Za-z0-9\/+]{40}/',
            'GitHub Token' => '/gh[pousr]_[A-Za-z0-9]{36}/',
            'Generic API Key' => '/api[_-]?key\s*[:=]\s*[\'"][a-zA-Z0-9]{20,}[\'"]/i',
            'Database Password' => '/db_password\s*[:=]\s*[\'"][^\'"]{8,}[\'"]/i',
            'JWT Secret' => '/jwt_secret\s*[:=]\s*[\'"][^\'"]{16,}[\'"]/i',
            'API Secret' => '/api_secret\s*[:=]\s*[\'"][^\'"]{16,}[\'"]/i',
            'Private Key' => '/-----BEGIN (RSA |EC |DSA )?PRIVATE KEY-----/',
        ];

        $excludeDirs = ['vendor', 'node_modules', 'storage', 'bootstrap/cache', '.git', 'tests'];
        $excludeFiles = ['.env.example', 'phpunit.xml', 'phpunit.xml.dist'];

        $phpFiles = $this->findFiles($filesystem, $projectRoot, $excludeDirs, $excludeFiles);

        foreach ($phpFiles as $file) {
            $content = $filesystem->read($file);
            $relativePath = $this->getRelativePath($projectRoot, $file);

            foreach ($patterns as $name => $pattern) {
                if (preg_match($pattern, $content)) {
                    // Find line number
                    $lines = explode("\n", $content);
                    $lineNumber = 1;
                    foreach ($lines as $line) {
                        if (preg_match($pattern, $line)) {
                            break;
                        }
                        $lineNumber++;
                    }

                    $findings[] = SecurityFinding::confirmedVulnerability(
                        ruleId: 'secrets.'.strtolower(str_replace(' ', '-', $name)),
                        title: "Exposed {$name} in source code",
                        category: 'code',
                        severity: SecuritySeverity::Critical,
                        evidence: "Pattern '{$name}' matched in {$file}",
                        description: "Hardcoded secret detected in source code. This secret is exposed to anyone with access to the repository.",
                        recommendation: "Remove the secret from source code and use environment variables or a secrets manager.",
                        file: $this->getRelativePath($projectRoot, $file),
                        line: $this->findLineNumber($content, $pattern),
                        confidence: 'high',
                    );
                }
            }
        }

        return $findings;
    }

    /**
     * @return list<string>
     */
    private function findFiles(Filesystem $filesystem, string $root, array $excludeDirs, array $excludeFiles): array
    {
        $files = [];
        $this->collectFiles($filesystem, $root, $files, $excludeDirs, $excludeFiles);
        return $files;
    }

    private function collectFiles(Filesystem $filesystem, string $dir, array &$files, array $excludeDirs, array $excludeFiles): void
    {
        if (! $filesystem->isDirectory($dir)) {
            return;
        }

        $baseName = basename($dir);
        if (in_array($baseName, $excludeDirs, true)) {
            return;
        }

        foreach ($filesystem->files($dir) as $file) {
            $baseName = basename($file);
            if (in_array($baseName, $excludeFiles, true)) {
                continue;
            }

            if (preg_match('/\.(php|env|yaml|yml|json|xml|ini|conf|config)$/i', $file)) {
                $files[] = $file;
            }
        }

        foreach ($filesystem->directories($dir) as $subDir) {
            $this->collectFiles($filesystem, $subDir, $files, $excludeDirs, $excludeFiles);
        }
    }

    private function getRelativePath(string $root, string $file): string
    {
        return ltrim(str_replace($root, '', $file), '/');
    }

    private function findLineNumber(string $content, string $pattern): ?int
    {
        $lines = explode("\n", $content);
        foreach ($lines as $i => $line) {
            if (preg_match($pattern, $line)) {
                return $i + 1;
            }
        }
        return null;
    }
}