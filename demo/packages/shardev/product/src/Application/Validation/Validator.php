<?php

declare(strict_types=1);

namespace Shardevcom\Product\Application\Validation;

use Shardevcom\Core\Exceptions\ValidationFailed;

/**
 * Minimal validator (required, string:N, integer:N, boolean, regex, digits)
 * without transport dependency or nested schemas. Rules pipe-separated
 * ('required|string:64'), one message per field, explicit null allowed in
 * optionals.
 */
final class Validator
{
    /**
     * @param array<string, mixed> $input
     * @param array<string, string> $rules
     * @return array<string, mixed>
     */
    public function check(array $input, array $rules): array
    {
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $ruleString) {
            $parts = explode('|', $ruleString);
            $hasField = array_key_exists($field, $input);
            $value = $input[$field] ?? null;

            $hasRequired = false;
            foreach ($parts as $rule) {
                if ($rule === 'required') {
                    $hasRequired = true;
                }
            }

            // An explicit null on an optional field is how a PATCH clears a
            // value, so it is a valid input, not a failed rule.
            if ($hasField && $value === null && !$hasRequired) {
                $validated[$field] = null;
                continue;
            }

            // If field is not present and not required, skip all validation
            if (!$hasField && !$hasRequired) {
                continue;
            }

            $hasError = false;
            foreach ($parts as $rule) {
                if (!$this->validateRule($field, $value, $rule, $input)) {
                    $errors[$field][] = $this->getMessage($field, $rule);
                    $hasError = true;
                }
            }

            // Always include required fields in validated, even if they failed validation
            if ($hasRequired || !$hasError) {
                $validated[$field] = $value;
            }
        }

        if ($errors) {
            throw new ValidationFailed($errors);
        }

        return $validated;
    }

    private function validateRule(string $field, mixed $value, string $rule, array $input): bool
    {
        if ($rule === 'required') {
            return $value !== null && $value !== '';
        }

        if (str_starts_with($rule, 'string:')) {
            $max = (int) substr($rule, 7);
            return is_string($value) && strlen($value) <= $max;
        }

        if (str_starts_with($rule, 'integer:')) {
            $min = (int) substr($rule, 8);
            return is_int($value) && $value >= $min;
        }

        if ($rule === 'boolean') {
            return is_bool($value);
        }

        if (str_starts_with($rule, 'regex:')) {
            $pattern = substr($rule, 6);
            return is_string($value) && preg_match($pattern, $value) === 1;
        }

        if (str_starts_with($rule, 'digits:')) {
            $len = (int) substr($rule, 7);
            return is_string($value) && strlen($value) === $len && ctype_digit($value);
        }

        return true; // unknown rule = pass
    }

    private function getMessage(string $field, string $rule): string
    {
        if ($rule === 'required') {
            return "The {$field} field is required.";
        }

        if (str_starts_with($rule, 'string:')) {
            return "The {$field} must be a string.";
        }

        if (str_starts_with($rule, 'integer:')) {
            return "The {$field} must be an integer.";
        }

        if ($rule === 'boolean') {
            return "The {$field} must be a boolean.";
        }

        return "The {$field} is invalid.";
    }
}