<?php

declare(strict_types=1);

namespace Shardevcom\Resources;

use Shardevcom\Core\Exceptions\ValidationFailed;

/**
 * Validated sparse fieldset (T-040). Empty means "all fields".
 */
final class FieldSet
{
    /**
     * @param list<string> $fields
     */
    private function __construct(private array $fields)
    {
    }

    /**
     * @param list<string> $input
     */
    public static function fromArray(array $input, ResourceDefinition $definition): self
    {
        $errors = [];
        $fields = [];

        foreach ($input as $field) {
            if (! is_string($field) || $field === '' || ! $definition->allowsField($field)) {
                $errors['fields.'.$field] = ['Field is not allowed for this resource.'];

                continue;
            }

            $fields[] = $field;
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        return new self(array_values(array_unique($fields)));
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return $this->fields;
    }
}
