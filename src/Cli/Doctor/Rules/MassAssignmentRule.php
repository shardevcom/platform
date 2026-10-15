<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor\Rules;

use Shardevcom\Cli\Doctor\SecurityFinding;
use Shardevcom\Cli\Doctor\SecurityRule;
use Shardevcom\Cli\Doctor\SecuritySeverity;
use Shardevcom\Cli\Filesystem\Filesystem;
use Shardevcom\Cli\Process\ProcessRunner;

/**
 * Checks for mass assignment vulnerabilities in models.
 */
final class MassAssignmentRule implements SecurityRule
{
    public function id(): string
    {
        return 'code.mass-assignment';
    }

    public function title(): string
    {
        return 'Models should protect against mass assignment';
    }

    public function category(): string
    {
        return 'code';
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

        // Find model files
        $modelFiles = $this->findModelFiles($filesystem, $projectRoot);

        foreach ($modelFiles as $file) {
            $content = $filesystem->read($file);
            $relativePath = $this->getRelativePath($projectRoot, $file);

            $findings = array_merge($findings, $this->checkModel($file, $content, $projectRoot));
        }

        if (empty($findings)) {
            return [
                SecurityFinding::preventiveAdvice(
                    ruleId: 'mass-assignment.ok',
                    title: 'Models appear to protect against mass assignment',
                    category: 'code',
                    severity: SecuritySeverity::Info,
                    evidence: 'Models appear to use $fillable or $guarded',
                    description: 'Models appear to properly protect against mass assignment.',
                    recommendation: 'Continue using $fillable or $guarded properties.',
                    confidence: 'medium',
                ),
            ];
        }

        return $findings;
    }

    /**
     * @return list<string>
     */
    private function findModelFiles(Filesystem $filesystem, string $root): array
    {
        $files = [];
        $dirs = [
            $root.'/app/Models',
            $root.'/app',
            $root.'/packages/*/src/Models',
        ];

        foreach ($dirs as $dir) {
            $this->collectModelFiles($filesystem, $dir, $files);
        }

        return $files;
    }

    private function collectModelFiles(Filesystem $filesystem, string $dir, array &$files): void
    {
        if (!$filesystem->isDirectory($dir)) {
            return;
        }

        foreach ($filesystem->files($dir) as $file) {
            if (preg_match('/\.php$/i', $file)) {
                $content = $filesystem->read($file);
                if (preg_match('/class\s+\w+\s+extends\s+Model/', $content) ||
                    preg_match('/use HasFactory/', $content) ||
                    preg_match('/implements.*Model/', $content)) {
                    $files[] = $file;
                }
            }
        }

        foreach ($filesystem->directories($dir) as $subDir) {
            $this->collectModelFiles($filesystem, $subDir, $files);
        }
    }

    /**
     * @return list<SecurityFinding>
     */
    private function checkModel(string $file, string $content, string $projectRoot): array
    {
        $findings = [];
        $relativePath = $this->getRelativePath($projectRoot, $file);

        // Check if model uses $fillable or $guarded
        $hasFillable = preg_match('/protected\s+\$fillable\s*=/', $content);
        $hasGuarded = preg_match('/protected\s+\$guarded\s*=/', $content);
        $hasUnguarded = preg_match('/protected\s+\$guarded\s*=\s*\[\s*\]/', $content);

        // Check if model uses $fillable without $guarded (or vice versa)
        if (!$hasFillable && !$hasGuarded) {
            return [
                SecurityFinding::insecureConfig(
                    ruleId: 'mass-assignment.no-protection',
                    title: 'Model missing $fillable or $guarded protection',
                    category: 'code',
                    severity: SecuritySeverity::Critical,
                    evidence: 'Model has neither $fillable nor $guarded property',
                    description: 'Model does not define $fillable or $guarded, making all attributes mass assignable.',
                    recommendation: 'Add $fillable array with allowed attributes or $guarded with protected attributes.',
                    file: $this->getRelativePath($projectRoot, $file),
                    confidence: 'high',
                ),
            ];
        }

        // Check for empty guarded (allows all)
        if ($hasUnguarded) {
            return [
                SecurityFinding::insecureConfig(
                    ruleId: 'mass-assignment.empty-guarded',
                    title: 'Model has empty $guarded array',
                    category: 'code',
                    severity: SecuritySeverity::Critical,
                    evidence: 'Model has $guarded = [] which allows all attributes',
                    description: 'Empty $guarded array means all attributes are mass assignable.',
                    recommendation: 'Remove empty $guarded or define specific protected attributes.',
                    file: $this->getRelativePath($projectRoot, $file),
                    confidence: 'high',
                ),
            ];
        }

        // Check for $fillable with '*' (allows all)
        if (preg_match('/\$fillable\s*=\s*\[\s*[\'"]\*[\'"]\s*\]/', $content)) {
            return [
                SecurityFinding::confirmedVulnerability(
                    ruleId: 'mass-assignment.fillable-star',
                    title: 'Model uses $fillable = ["*"] allowing all attributes',
                    category: 'code',
                    severity: SecuritySeverity::Critical,
                    evidence: 'Model uses $fillable = ["*"] which allows all attributes',
                    description: 'Using $fillable = ["*"] defeats mass assignment protection.',
                    recommendation: 'Explicitly list allowed attributes in $fillable array.',
                    file: $this->getRelativePath($projectRoot, $file),
                    confidence: 'high',
                ),
            ];
        }

        // Check for sensitive fields in fillable
        $sensitiveFields = ['password', 'secret', 'token', 'api_key', 'secret_key', 'private_key', 'remember_token'];
        if ($hasFillable) {
            foreach ($sensitiveFields as $sensitive) {
                if (preg_match("/\$fillable\s*=.*['\"]{$sensitive}['\"]/", $content)) {
                    return [
                        SecurityFinding::insecureConfig(
                            ruleId: 'mass-assignment.sensitive-field',
                            title: "Sensitive field '{$sensitive}' in \$fillable",
                            category: 'code',
                            severity: SecuritySeverity::High,
                            evidence: "Sensitive field '{$sensitive}' found in \$fillable",
                            description: "Sensitive field '{$sensitive}' is mass assignable, which could allow unauthorized modification.",
                            recommendation: "Add '{$sensitive}' to \$guarded or remove from \$fillable.",
                            file: $this->getRelativePath($projectRoot, $file),
                            confidence: 'high',
                        ),
                    ];
                }
            }
        }

        return [];
    }

    private function getRelativePath(string $root, string $file): string
    {
        return ltrim(str_replace($root, '', $file), '/');
    }
}