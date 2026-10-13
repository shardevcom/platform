<?php

declare(strict_types=1);

namespace Shardevcom\Product\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import reader for getting headers and row count without processing.
 */
final class ProductsImportReader implements ToCollection, WithHeadingRow
{
    private int $rowCount = 0;

    private array $headers = [];

    public function collection(Collection $rows): void
    {
        $this->rowCount = $rows->count();

        if ($rows->isNotEmpty()) {
            $this->headers = $this->normalizeHeaders(array_keys($rows->first()->toArray()));
        }
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    /**
     * @return list<string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * T-CASA-016: headers are normalized before any transformRow call —
     * UTF-8 BOM stripped, trimmed, lowercased, duplicates dropped — so
     * " SKU ", "SKU" or a BOM-prefixed "Name" all count as the same column.
     *
     * @param list<string> $headers
     * @return list<string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        $seen = [];

        foreach ($headers as $header) {
            $key = mb_strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $header)));

            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = $key;
        }

        return $normalized;
    }
}