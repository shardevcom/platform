<?php

declare(strict_types=1);

namespace Shardevcom\Resources;

use Shardevcom\Core\Exceptions\ValidationFailed;

/**
 * Validated exact-match filters (T-040).
 *
 * Keys must be allow-listed in the definition; values are scalars or flat
 * scalar lists (IN semantics). Anything else — unknown keys, nested
 * structures, closures — is a ValidationFailed, never silently ignored.
 */
final class FilterSet
{
    /**
     * @param array<string, scalar|list<scalar>> $filters
     */
    private function __construct(private array $filters)
    {
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function fromArray(array $input, ResourceDefinition $definition): self
    {
        $errors = [];
        $filters = [];

        foreach ($input as $key => $value) {
            if (! $definition->allowsFilter((string) $key)) {
                $errors['filters.'.$key] = ['Filter is not allowed for this resource.'];

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $filters[(string) $key] = $value;

                continue;
            }

            if (is_array($value) && self::isFlatScalarList($value)) {
                $filters[(string) $key] = array_values($value);

                continue;
            }

            $errors['filters.'.$key] = ['Filter value must be a scalar or a flat scalar list.'];
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        return new self($filters);
    }

    /**
     * @return array<string, scalar|list<scalar>|null>
     */
    public function all(): array
    {
        return $this->filters;
    }

    public function count(): int
    {
        return count($this->filters);
    }

    /**
     * Sequential scalar lists only (IN semantics). Associative arrays are
     * rejected: they look like operators waiting to be invented.
     *
     * @param array<mixed> $values
     */
    private static function isFlatScalarList(array $values): bool
    {
        if ($values === [] || ! array_is_list($values)) {
            return false;
        }

        foreach ($values as $value) {
            if (! is_scalar($value) && $value !== null) {
                return false;
            }
        }

        return true;
    }
}
