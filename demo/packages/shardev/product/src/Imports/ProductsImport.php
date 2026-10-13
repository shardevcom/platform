<?php

declare(strict_types=1);

namespace Shardevcom\Product\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Shardevcom\Product\Repositories\ProductRepository;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\ResourceAction;
use Shardevcom\Product\Application\Commands\CreateProduct;

/**
 * Import products from Excel/CSV.
 *
 * Uses Laravel Excel's ToCollection with validation.
 */
final class ProductsImport implements ToCollection, WithHeadingRow, WithValidation
{
    /** @var list<array<string, mixed>> */
    private array $errors = [];

    private int $importedCount = 0;

    public function __construct(
        private readonly ProductRepository $products,
        private readonly CreateProduct $createProduct,
    ) {
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            try {
                $data = $this->transformRow($row);
                $context = ApplicationContext::create(['userId' => 'import']);

                $this->createProduct->execute($data, $context);
                $this->importedCount++;
            } catch (\Throwable $e) {
                $this->errors[] = "Row {$row->getIndex()}: {$e->getMessage()}";
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function transformRow(Collection $row): array
    {
        return [
            'sku' => $row['sku'] ?? null,
            'name' => $row['name'] ?? null,
            'ean' => $row['ean'] ?? null,
            'brand_id' => $row['brand_id'] ?? null,
            'active' => $this->parseBoolean($row['active'] ?? true),
        ];
    }

    private function parseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        return in_array(strtolower((string) $value), ['true', '1', 'yes', 'si', 'sí'], true);
    }

    public function rules(): array
    {
        return [
            'sku' => 'required|string|max:100',
            'name' => 'required|string|max:255',
            'ean' => 'nullable|string|max:100',
            'brand_id' => 'nullable|integer|exists:brands,id',
            'active' => 'nullable|boolean',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'sku.required' => 'SKU is required',
            'name.required' => 'Name is required',
            'brand_id.exists' => 'Brand does not exist',
        ];
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    /**
     * @return list<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}