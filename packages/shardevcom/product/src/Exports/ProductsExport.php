<?php

declare(strict_types=1);

namespace Shardevcom\Product\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export products to Excel/CSV.
 */
final class ProductsExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    /** @var list<array<string, mixed>> */
    private array $rows;

    private string $format;

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(array $rows, string $format = 'xlsx')
    {
        $this->rows = $rows;
        $this->format = $format;
    }

    public function collection(): \Illuminate\Support\Collection
    {
        return collect($this->rows);
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['ID', 'SKU', 'EAN', 'Name', 'Brand ID', 'Active', 'Created At', 'Updated At'];
    }

    /**
     * @param array<string, mixed> $row
     * @return list<mixed>
     */
    public function map($row): array
    {
        return [
            $row['id'] ?? '',
            $row['sku'] ?? '',
            $row['ean'] ?? '',
            $row['name'] ?? '',
            $row['brand_id'] ?? '',
            $row['active'] ?? false ? 'Sí' : 'No',
            $row['created_at'] ?? '',
            $row['updated_at'] ?? '',
        ];
    }

    public function title(): string
    {
        return 'Products';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function getFormat(): string
    {
        return $this->format;
    }
}