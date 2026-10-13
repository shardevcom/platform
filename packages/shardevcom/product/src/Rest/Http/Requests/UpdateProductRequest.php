<?php

declare(strict_types=1);

namespace Shardevcom\Product\Rest\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Shardevcom\Product\Application\Data\UpdateProductData;

/**
 * FormRequest for updating a product.
 *
 * Validates input and converts to UpdateProductData DTO.
 * The actual validation rules are defined in UpdateProductData.
 */
final class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is handled by the repository lifecycle
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return UpdateProductData::rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return UpdateProductData::messages();
    }

    public function toDTO(): \Shardevcom\Product\Application\Data\UpdateProductData
    {
        return UpdateProductData::fromArray($this->validated());
    }
}