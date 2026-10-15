<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor;

/**
 * Represents a security finding with detailed metadata.
 */
final class SecurityFinding
{
    /**
     * @param array<string, string> $references
     */
    public function __construct(
        public readonly string $ruleId,
        public readonly string $title,
        public readonly string $category,
        public readonly SecuritySeverity $severity,
        public readonly string $confidence, // high, medium, low
        public readonly ?string $file = null,
        public readonly ?int $line = null,
        public readonly string $evidence = '',
        public readonly string $description = '',
        public readonly string $exploitConditions = '',
        public readonly string $impact = '',
        public readonly string $recommendation = '',
        public readonly array $references = [],
        public readonly FindingType $type = FindingType::PotentialRisk,
    ) {}

    public static function confirmedVulnerability(
        string $ruleId,
        string $title,
        string $category,
        SecuritySeverity $severity,
        string $evidence,
        string $description,
        string $recommendation,
        ?string $file = null,
        ?int $line = null,
        string $confidence = 'high',
        array $references = []
    ): self {
        return new self(
            ruleId: $ruleId,
            title: $title,
            category: $category,
            severity: $severity,
            confidence: $confidence,
            file: $file,
            line: $line,
            evidence: $evidence,
            description: $description,
            recommendation: $recommendation,
            type: FindingType::ConfirmedVulnerability,
        );
    }

    public static function potentialRisk(
        string $ruleId,
        string $title,
        string $category,
        SecuritySeverity $severity,
        string $evidence,
        string $description,
        string $recommendation,
        ?string $file = null,
        ?int $line = null,
        string $confidence = 'medium',
        array $references = []
    ): self {
        return new self(
            ruleId: $ruleId,
            title: $title,
            category: $category,
            severity: $severity,
            confidence: $confidence,
            file: $file,
            line: $line,
            evidence: $evidence,
            description: $description,
            recommendation: $recommendation,
            type: FindingType::PotentialRisk,
        );
    }

    public static function insecureConfig(
        string $ruleId,
        string $title,
        string $category,
        SecuritySeverity $severity,
        string $evidence,
        string $description,
        string $recommendation,
        ?string $file = null,
        ?int $line = null,
        string $confidence = 'high',
        array $references = []
    ): self {
        return new self(
            ruleId: $ruleId,
            title: $title,
            category: $category,
            severity: $severity,
            confidence: $confidence,
            file: $file,
            line: $line,
            evidence: $evidence,
            description: $description,
            recommendation: $recommendation,
            type: FindingType::InsecureConfiguration,
        );
    }

    public static function preventiveAdvice(
        string $ruleId,
        string $title,
        string $category,
        SecuritySeverity $severity,
        string $evidence,
        string $description,
        string $recommendation,
        ?string $file = null,
        ?int $line = null,
        string $confidence = 'low',
        array $references = []
    ): self {
        return new self(
            ruleId: $ruleId,
            title: $title,
            category: $category,
            severity: $severity,
            confidence: $confidence,
            file: $file,
            line: $line,
            evidence: $evidence,
            description: $description,
            recommendation: $recommendation,
            type: FindingType::PreventiveAdvice,
        );
    }

    /**
     * @return array<string, string>
     */
    public function toRow(): array
    {
        $location = $this->file !== null
            ? ($this->line !== null ? "{$this->file}:{$this->line}" : $this->file)
            : 'N/A';

        return [
            $this->severity->value,
            $this->type->value,
            $this->ruleId,
            $this->title,
            $location,
            $this->evidence,
        ];
    }
}