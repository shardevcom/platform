<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor\Rules;

use Shardevcom\Cli\Doctor\SecurityFinding;
use Shardevcom\Cli\Doctor\SecurityRule;
use Shardevcom\Cli\Doctor\SecuritySeverity;
use Shardevcom\Cli\Filesystem\Filesystem;
use Shardevcom\Cli\Process\ProcessRunner;

/**
 * Checks for vulnerable dependencies using composer audit.
 */
final class DependencyAuditRule implements SecurityRule
{
    public function id(): string
    {
        return 'dependencies.vulnerable-packages';
    }

    public function title(): string
    {
        return 'Dependencies should not have known vulnerabilities';
    }

    public function category(): string
    {
        return 'dependencies';
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

        // Check if composer audit is available
        $result = $process->run('composer audit --format=json', $projectRoot);

        if ($result->failed()) {
            // composer audit not available or failed
            $findings[] = SecurityFinding::potentialRisk(
                ruleId: 'dependencies.audit-unavailable',
                title: 'Composer audit not available',
                category: 'dependencies',
                severity: SecuritySeverity::Low,
                evidence: 'composer audit command failed or not available',
                description: 'Could not run composer audit to check for vulnerable dependencies.',
                recommendation: 'Ensure composer is installed and up to date. Run composer audit manually.',
                confidence: 'low',
            );

            return $findings;
        }

        $output = $result->output;
        $data = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                SecurityFinding::potentialRisk(
                    ruleId: 'dependencies.audit-parse-failed',
                    title: 'Could not parse composer audit output',
                    category: 'dependencies',
                    severity: SecuritySeverity::Low,
                    evidence: 'Failed to parse JSON output from composer audit',
                    description: 'Could not parse the output from composer audit.',
                    recommendation: 'Run composer audit manually to check for vulnerabilities.',
                    confidence: 'low',
                ),
            ];
        }

        $findings = [];

        if (isset($data['packages'])) {
            foreach ($data['packages'] as $packageName => $vulnerabilities) {
                foreach ($vulnerabilities as $vuln) {
                    $severity = $this->mapSeverity($vuln['severity'] ?? 'unknown');

                    $findings[] = SecurityFinding::confirmedVulnerability(
                        ruleId: 'dependencies.'.$vuln['cve'] ?? $this->id(),
                        title: "Vulnerable dependency: {$vuln['package']} {$vuln['version']}",
                        category: 'dependencies',
                        severity: $this->mapSeverity($vuln['severity'] ?? 'unknown'),
                        evidence: "CVE: {$vuln['cve']} - {$vuln['title']}",
                        description: "Package {$vuln['package']} version {$vuln['version']} has known vulnerability: {$vuln['title']}",
                        recommendation: "Update {$vuln['package']} to version {$vuln['patched_versions']} or later.",
                        references: [$vuln['cve'] ?? ''],
                        confidence: 'high',
                    );
                }
            }
        }

        if (isset($data['advisories'])) {
            foreach ($data['advisories'] as $advisory) {
                $findings[] = SecurityFinding::confirmedVulnerability(
                    ruleId: 'dependencies.'.$advisory['cve'] ?? $this->id(),
                    title: "Advisory: {$advisory['title']}",
                    category: 'dependencies',
                    severity: $this->mapSeverity($advisory['severity'] ?? 'unknown'),
                    evidence: "CVE: {$advisory['cve']}",
                    description: $advisory['description'],
                    recommendation: "Update affected packages as per advisory.",
                    references: [$advisory['cve'] ?? ''],
                    confidence: 'high',
                );
            }
        }

        if ($findings === []) {
            return [
                SecurityFinding::preventiveAdvice(
                    ruleId: 'dependencies.ok',
                    title: 'No known vulnerable dependencies',
                    category: 'dependencies',
                    severity: SecuritySeverity::Info,
                    evidence: 'composer audit found no vulnerabilities',
                    description: 'No known vulnerabilities found in dependencies.',
                    recommendation: 'Run composer audit regularly in CI/CD pipeline.',
                    confidence: 'high',
                ),
            ];
        }

        return $findings;
    }

    private function mapSeverity(string $severity): SecuritySeverity
    {
        return match (strtolower($severity)) {
            'critical' => SecuritySeverity::Critical,
            'high' => SecuritySeverity::High,
            'moderate', 'medium' => SecuritySeverity::Medium,
            'low' => SecuritySeverity::Low,
            default => SecuritySeverity::Medium,
        };
    }
}