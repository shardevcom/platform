<?php

declare(strict_types=1);

namespace Shardevcom\Product\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Psr\Log\LoggerInterface;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Product\Models\ProductImport;
use Shardevcom\Product\Models\ProductImportRow;
use Shardevcom\Product\Imports\ProductsImportChunk;

/**
 * Process a chunk of product import rows.
 */
final class ProcessProductImportChunk implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $importId,
        private readonly int $offset,
        private readonly int $limit,
    ) {
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        $import = ProductImport::findOrFail($this->importId);

        // Mark import as processing if first chunk
        if ($import->status === 'pending') {
            $import->markProcessing();
        }

        $path = Storage::disk($import->disk)->path($import->file_path);
        $format = $import->format;

        // Read chunk from file
        $importReader = new ProductsImportChunk($this->offset, $this->limit);
        Excel::import(
            $importReader,
            $path,
            null,
            mb_strtolower($format) === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX,
        );

        $rows = $importReader->getRows();

        if ($rows === []) {
            return;
        }

        $successful = 0;
        $failed = 0;

        foreach ($rows as $index => $row) {
            // Reading starts at physical row (offset + 2): row 1 is the header,
            // so the first data row of the whole file is row 2 and a chunk at
            // data-offset N records physical rows offset+2 .. offset+1+limit.
            $rowNumber = $this->offset + $index + 2;

            try {
                $data = $this->transformRow($row);

                // Validate row
                $this->validateRow($data);

                // Create product via use case. The row runs with the SAME granted
                // permissions the submitter brought to the request: an import
                // is not a privilege escalation vector, and it cannot do more
                // (or less) than the user could by hand.
                $context = ApplicationContext::create([
                    'userId' => $import->created_by !== null ? (string) $import->created_by : 'import',
                    'permissions' => $import->granted_permissions ?? [],
                ]);
                $product = app(\Shardevcom\Product\Application\Commands\CreateProduct::class)
                    ->execute($data, $context);

                // Record success
                ProductImportRow::createSuccess($this->importId, $rowNumber, $data, $product['id']);

                $successful++;
            } catch (\Throwable $e) {
                $errorCode = $this->getErrorCode($e);
                $errorDetails = $this->getErrorDetails($e);

                // The raw spreadsheet row and the exception message can carry
                // values that must never reach the logs (T-CASA-024); the
                // redaction processor scrubs the sensitive columns before
                // anything is written.
                app(LoggerInterface::class)->warning('Import row failed', [
                    'action' => 'import',
                    'resource' => 'products',
                    'row_number' => $rowNumber,
                    'error_code' => $errorCode,
                    'error_message' => $e->getMessage(),
                    'row' => $row->toArray(),
                ]);

                // Record failure
                ProductImportRow::createFailure(
                    $this->importId,
                    $rowNumber,
                    $data ?? $row->toArray(),
                    $errorCode,
                    $e->getMessage(),
                    $errorDetails
                );

                $failed++;
            }
        }

        // Update import counters atomically
        DB::transaction(function () use ($import, $successful, $failed, $rows) {
            $import->increment('processed_rows', count($rows));
            $import->increment('successful_rows', $successful);
            $import->increment('failed_rows', $failed);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function transformRow(array $row): array
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

    /**
     * @param array<string, mixed> $data
     */
    private function validateRow(array $data): void
    {
        $errors = [];

        if (empty($data['sku'])) {
            $errors['sku'] = 'SKU is required';
        }

        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        }

        if (!empty($data['brand_id'])) {
            $exists = DB::table('brands')->where('id', $data['brand_id'])->exists();
            if (!$exists) {
                $errors['brand_id'] = 'Brand does not exist';
            }
        }

        if ($errors !== []) {
            throw new \Illuminate\Validation\ValidationException(
                \Illuminate\Validation\Factory::make($errors)->make($data, [])
            );
        }
    }

    private function getErrorCode(\Throwable $e): string
    {
        return match (true) {
            $e instanceof \Illuminate\Validation\ValidationException => 'VALIDATION_FAILED',
            $e instanceof \Shardevcom\Core\Exceptions\Conflict => 'CONFLICT',
            $e instanceof \Shardevcom\Core\Exceptions\BusinessRuleViolation => 'BUSINESS_RULE_VIOLATION',
            default => 'IMPORT_ERROR',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function getErrorDetails(\Throwable $e): array
    {
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return ['errors' => $e->errors()];
        }

        if (method_exists($e, 'context')) {
            return $e->context() ?? [];
        }

        return ['exception' => get_class($e)];
    }
}