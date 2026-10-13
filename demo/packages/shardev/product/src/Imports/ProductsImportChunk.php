<?php

declare(strict_types=1);

namespace Shardevcom\Product\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Import a specific chunk of rows from Excel/CSV.
 */
final class ProductsImportChunk implements ToCollection, WithHeadingRow, WithStartRow, WithLimit
{
    /** @var list<array<string, mixed>> */
    private array $rows = [];

    public function __construct(
        private readonly int $startRow,
        private readonly int $limit,
    ) {
    }

    public function collection(Collection $rows): void
    {
        $this->rows = $rows->toArray();
    }

    public function getRows(): array
    {
        return $this->rows;
    }

    public function startRow(): int
    {
        // +1 for header row, +1 because startRow is 1-based
        return $this->startRow + 2;
    }

    public function limit(): int
    {
        return $this->limit;
    }
}