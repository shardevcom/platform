<?php

declare(strict_types=1);

namespace Shardevcom\Resources;

use Shardevcom\Core\Exceptions\ValidationFailed;

/**
 * Validated ordering (T-040).
 *
 * Items are field names with an optional '-' prefix for descending
 * ('name', '-created_at'). Fields must be allow-listed; direction is
 * normalized to 'asc'|'desc'.
 */
final class SortSet
{
    /**
     * @param array<string, string> $sorts field => direction
     */
    private function __construct(private array $sorts)
    {
    }

    /**
     * @param list<string> $input
     */
    public static function fromArray(array $input, ResourceDefinition $definition): self
    {
        $errors = [];
        $sorts = [];

        foreach ($input as $item) {
            if (! is_string($item) || $item === '') {
                $errors['sort.'] = ['Sort entries must be non-empty strings.'];

                continue;
            }

            $direction = str_starts_with($item, '-') ? 'desc' : 'asc';
            $field = ltrim($item, '-');

            if (! $definition->allowsSort($field)) {
                $errors['sort.'.$field] = ['Sort is not allowed for this resource.'];

                continue;
            }

            $sorts[$field] = $direction;
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        return new self($sorts);
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->sorts;
    }
}
