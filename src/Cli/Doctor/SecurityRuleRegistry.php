<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor;

/**
 * Registry for security rules.
 *
 * Allows rules to be registered and retrieved by category or all at once.
 */
final class SecurityRuleRegistry
{
    /** @var list<SecurityRule> */
    private array $rules = [];

    public function register(SecurityRule $rule): void
    {
        $this->rules[] = $rule;
    }

    /**
     * @return list<SecurityRule>
     */
    public function all(): array
    {
        return $this->rules;
    }

    /**
     * @return list<SecurityRule>
     */
    public function byCategory(string $category): array
    {
        return array_values(array_filter(
            $this->rules,
            fn (SecurityRule $rule): bool => $rule->category() === $category
        ));
    }

    /**
     * @return list<string>
     */
    public function categories(): array
    {
        $categories = array_map(
            fn (SecurityRule $rule): string => $rule->category(),
            $this->rules
        );

        return array_values(array_unique($categories));
    }

    /**
     * @return list<SecurityRule>
     */
    public function bySeverity(SecuritySeverity $severity): array
    {
        return array_values(array_filter(
            $this->rules,
            fn (SecurityRule $rule): bool => $rule->defaultSeverity() === $severity
        ));
    }
}