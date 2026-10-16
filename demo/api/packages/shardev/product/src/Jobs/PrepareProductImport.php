<?php

declare(strict_types=1);

namespace Shardevcom\Product\Jobs;

use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Shardevcom\Product\Imports\ProductsImportReader;
use Shardevcom\Product\Models\ProductImport;

/**
 * Async pre-flight of an import (T-CASA-015 continuation).
 *
 * The submission endpoint must respond before the file is read: this job does
 * the one full scan (headers + row count) off the request, validates the
 * required columns, and only then dispatches the chunk batch. The chunk batch
 * carries its finalizer as a batch "finally" callback, so a session can never
 * be left in "processing" because someone forgot to chain finalization.
 */
final class PrepareProductImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $importId,
    ) {
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        $import = ProductImport::findOrFail($this->importId);

        try {
            $fullPath = Storage::disk($import->disk)->path($import->file_path);

            $reader = new ProductsImportReader();
            Excel::import($reader, $fullPath, null, $this->readerType($import->format));

            if (! $this->validHeaders($reader->getHeaders())) {
                $import->markFailed(
                    'Missing required columns: '.implode(', ', $this->missingHeaders($reader->getHeaders()))
                );

                return;
            }

            $totalRows = $reader->getRowCount();
            $import->update(['total_rows' => $totalRows]);

            if ($totalRows === 0) {
                $import->markCompleted();

                return;
            }

            $maxRows = (int) config('platform.import.max_rows', 10_000);

            if ($totalRows > $maxRows) {
                $import->markFailed(
                    'Row count '.$totalRows.' exceeds the maximum of '.$maxRows.' rows.',
                );

                return;
            }

            $this->dispatchChunks($import, $totalRows);
        } catch (\Throwable $failure) {
            $import->markFailed($failure->getMessage());
        }
    }

    private function dispatchChunks(ProductImport $import, int $totalRows): void
    {
        $chunkSize = 100;
        $jobs = [];
        $importId = $this->importId;

        for ($offset = 0; $offset < $totalRows; $offset += $chunkSize) {
            $jobs[] = new ProcessProductImportChunk(
                $importId,
                $offset,
                min($chunkSize, $totalRows - $offset),
            );
        }

        Bus::batch($jobs)
            ->name("product-import-{$importId}")
            ->onQueue('imports')
            ->finally(fn (Batch $batch) => FinalizeProductImport::dispatch($importId))
            ->dispatch();
    }

    /**
     * @param list<string> $headers
     */
    private function validHeaders(array $headers): bool
    {
        return array_diff(['sku', 'name'], $headers) === [];
    }

    /**
     * @param list<string> $headers
     * @return list<string>
     */
    private function missingHeaders(array $headers): array
    {
        return array_values(array_diff(['sku', 'name'], $headers));
    }

    private function readerType(string $format): string
    {
        return mb_strtolower($format) === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX;
    }
}