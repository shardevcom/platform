<?php

declare(strict_types=1);

namespace Shardevcom\Product\Application\Data;

use Shardevcom\Product\Application\Validation\Validator;

/**
 * Validated input for creating a product (T-060).
 *
 * This is the SINGLE validation point: a REST request, a GraphQL mutation or
 * a console command all build the DTO the same way, so the rules cannot drift
 * per adapter. fromArray() throws ValidationFailed with field errors before
 * the repository is ever called.
 */
final class CreateProductData
{
    /**
     * @param array{sku: string, name: string, ean?: string|null, brand_id?: int|null, active?: bool} $attributes
     */
    private function __construct(
        public readonly string $sku,
        public readonly string $name,
        public readonly ?string $ean = null,
        public readonly ?int $brandId = null,
        public readonly bool $active = true,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function fromArray(array $input): self
    {
        $validated = (new Validator($input, self::rules()))->check($input, self::rules());

        return new self(
            sku: $validated['sku'],
            name: $validated['name'],
            ean: $validated['ean'] ?? null,
            brandId: $validated['brand_id'] ?? null,
            active: $validated['active'] ?? true,
        );
    }

    /**
     * @return array{sku: string, name: string, active?: bool, ean?: string|null, brand_id?: int|null}
     */
    public static function rules(): array
    {
        return [
            'sku' => 'required|string:64',
            'name' => 'required|string:255',
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
            'sku.required' => 'The SKU field is required.',
            'sku.string' => 'The SKU must be a string.',
            'name.required' => 'The name field is required.',
            'name.string' => 'The name must be a string.',
        ];
    }

    /**
     * Wire format handed to the repository. Snake_case because it is the
     * column vocabulary the persistence adapter declares; undeclared columns
     * are rejected there, so this list is the honest surface.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'sku' => $this->sku,
            'name' => $this->name,
            'ean' => $this->ean,
            'brand_id' => $this->brandId,
            'active' => $this->active,
        ];
    }
}