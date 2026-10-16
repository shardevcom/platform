<?php

declare(strict_types=1);

namespace Shardevcom\Product\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Shardevcom\Product\Models\ProductImport;

/**
 * Finalize product import after all chunks are processed.
 */
final class FinalizeProductImport implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $importId,
    ) {
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        $import = ProductImport::findOrFail($this->importId);

        $status = $import->failed_rows > 0 && $import->successful_rows === 0
            ? 'failed'
            : ($import->failed_rows > 0 ? 'completed_with_errors' : 'completed');

        $import->update([
            'status' => $status,
            'completed_at' => now(),
            'error_summary' => $import->failed_rows > 0 ? [
                'total_failed' => $import->failed_rows,
                'total_successful' => $import->successful_rows,
                'message' => $status === 'failed'
                    ? 'All rows failed to import'
                    : 'Some rows failed to import',
            ] : null,
        ]);
    }
}