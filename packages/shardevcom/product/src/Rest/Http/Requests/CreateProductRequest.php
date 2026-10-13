<?php

declare(strict_types=1);

namespace Shardevcom\Product\Rest\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Shardevcom\Product\Application\Data\CreateProductData;

/**
 * FormRequest for creating a product.
 *
 * Validates input and converts to CreateProductData DTO.
 * The actual validation rules are defined in CreateProductData.
 */
final class CreateProductRequest extends FormRequest
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
        return CreateProductData::rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return CreateProductData::messages();
    }

    public function toDTO(): \Shardevcom\Product\Application\Data\CreateProductData
    {
        return CreateProductData::fromArray($this->validated());
    }
}