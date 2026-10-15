<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor;

use Shardevcom\Cli\Filesystem\Filesystem;
use Shardevcom\Cli\Process\ProcessRunner;

/**
 * Security audit suite: runs registered security rules against the project.
 */
final class SecuritySuite implements CheckSuite
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly ProcessRunner $process,
        private readonly string $projectRoot,
        private readonly SecurityRuleRegistry $registry,
        private readonly ?SecuritySeverity $minSeverity = null,
        private readonly ?array $categories = null,
    ) {}

    public function name(): string
    {
        return 'Security';
    }

    /**
     * @return list<CheckResult>
     */
    public function run(): array
    {
        $results = [];

        $rules = $this->getFilteredRules();

        foreach ($this->registry->all() as $rule) {
            try {
                $findings = $rule->check(
                    $this->filesystem,
                    $this->process,
                    $this->projectRoot
                );

                foreach ($findings as $finding) {
                    if ($this->minSeverity !== null && $finding->severity->priority() < $this->minSeverity->priority()) {
                        continue;
                    }

                    $results[] = $this->findingToResult($finding);
                }
            } catch (\Throwable $exception) {
                $results[] = CheckResult::fail(
                    'Rule crashed: '.$rule->id(),
                    $rule->id().': '.$exception->getMessage()
                );
            }
        }

        return $results;
    }

    private function getFilteredRules(): array
    {
        $rules = $this->registry->all();

        if ($this->categories !== null && $this->categories !== []) {
            $rules = array_filter($this->registry->all(), function (SecurityRule $rule) {
                return in_array($rule->category(), $this->categories ?? [], true);
            });
        }

        return $rules;
    }

    private function findingToResult(SecurityFinding $finding): CheckResult
    {
        $passed = $finding->type === FindingType::PreventiveAdvice
            || $finding->severity === SecuritySeverity::Info;

        $detail = $this->formatDetail($finding);

        return CheckResult::of(
            $finding->title,
            $passed,
            $detail
        );
    }

    private function formatDetail(SecurityFinding $finding): string
    {
        $parts = [];

        $parts[] = 'Severity: '.$finding->severity->value;
        $parts[] = 'Type: '.$finding->type->value;
        $parts[] = 'Confidence: '.$finding->confidence;

        if ($finding->file !== null) {
            $parts[] = 'File: '.($finding->line !== null ? "{$finding->file}:{$finding->line}" : $finding->file);
        }

        if ($finding->evidence !== '') {
            $parts[] = 'Evidence: '.$finding->evidence;
        }

        if ($finding->description !== '') {
            $parts[] = 'Description: '.$finding->description;
        }

        if ($finding->recommendation !== '') {
            $parts[] = 'Recommendation: '.$finding->recommendation;
        }

        return implode('; ', $parts);
    }
}