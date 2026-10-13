<?php

declare(strict_types=1);

namespace Shardevcom\Product\Application\Data;

use Shardevcom\Core\Exceptions\ValidationFailed;
use Shardevcom\Product\Application\Validation\Validator;

/**
 * Validated patch for a product (T-060).
 *
 * A PATCH must be able to tell "field not provided" from "field set to null",
 * so the DTO keeps the partial nature of the input instead of collapsing it
 * into a full object. Every field is optional; an empty patch is rejected
 * because it would mean a pointless write plus a pointless audit entry.
 */
final class UpdateProductData
{
    /**
     * @param array<string, mixed> $attributes only the provided fields, validated
     */
    private function __construct(
        public readonly array $attributes,
        public readonly array $provided,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function fromArray(array $input): self
    {
        $rules = self::rules();
        $validator = new Validator($input, $rules);
        $attributes = $validator->check($input, $rules);
        $provided = array_keys($attributes);

        if ($provided === []) {
            throw new ValidationFailed(
                ['_' => ['At least one field must be provided.']],
                'The given data was invalid.',
            );
        }

        return new self($attributes, $provided);
    }

    /**
     * @return array{sku?: string, name?: string, active?: bool, ean?: string|null, brand_id?: int|null}
     */
    public static function rules(): array
    {
        return [
            'sku' => 'string:64',
            'name' => 'string:255',
            'ean' => 'string:32',
            'brand_id' => 'integer:1',
            'active' => 'boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'sku.string' => 'The SKU must be a string.',
            'name.string' => 'The name must be a string.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return list<string>
     */
    public function providedFields(): array
    {
        return $this->provided;
    }
}