<?php

declare(strict_types=1);

namespace Shardevcom\Product\Repositories;

use Shardevcom\Core\Exceptions\ResourceNotFound;
use Shardevcom\Product\Models\ProductImport;
use Shardevcom\Product\Models\ProductImportRow;

/**
 * Import session bookkeeping (product_imports / product_import_rows).
 *
 * Not a CRUD passthrough: it owns the session lifecycle the chunk jobs
 * depend on (start, status card, row errors). The {products}.import
 * authorization gate lives in the ProductRepository lifecycle funnel, so
 * this class never authorizes anything. It does, however, SCOPE the status
 * and error reads to the session's creator (T-CASA-029): a session belongs
 * to whoever submitted it, and reading another caller's session answers 404
 * (RESOURCE_NOT_FOUND) so the endpoint is not an oracle for existing ids.
 */
final class ProductImportRepository
{
    public function __construct()
    {
    }

    public function start(
        string $importId,
        string $filePath,
        string $disk,
        string $format,
        int $totalRows,
        ?int $createdBy,
        array $grantedPermissions = [],
    ): ProductImport {
        return ProductImport::create([
            'id' => $importId,
            'file_path' => $filePath,
            'disk' => $disk,
            'format' => $format,
            'total_rows' => $totalRows,
            'status' => 'pending',
            'started_at' => now(),
            'created_by' => $createdBy,
            'granted_permissions' => $grantedPermissions,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function status(string $importId, ?string $subjectUserId = null): array
    {
        $import = ProductImport::findOrFail($importId);
        $this->assertSubjectMayRead($import, $subjectUserId);

        return [
            'import_id' => $import->id,
            'status' => $import->status,
            'total_rows' => $import->total_rows,
            'processed_rows' => $import->processed_rows,
            'successful_rows' => $import->successful_rows,
            'failed_rows' => $import->failed_rows,
            'started_at' => optional($import->started_at)->toIso8601String(),
            'completed_at' => optional($import->completed_at)->toIso8601String(),
            'error_summary' => $import->error_summary,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function errors(string $importId, int $limit = 100, ?string $subjectUserId = null): array
    {
        $import = ProductImport::findOrFail($importId);
        $this->assertSubjectMayRead($import, $subjectUserId);

        return ProductImportRow::query()
            ->where('import_id', $importId)
            ->where('status', 'failed')
            ->orderBy('row_number')
            ->limit($limit)
            ->get()
            ->map(static fn (ProductImportRow $row): array => [
                'row_number' => $row->row_number,
                'error_code' => $row->error_code,
                'error_message' => $row->error_message,
                'error_details' => $row->error_details,
            ])
            ->values()
            ->all();
    }

    /**
     * Data scoping, not authorization (T-CASA-029). A session that recorded a
     * creator is only visible to that creator; sessions without a recorded
     * creator (system or anonymous) stay readable by any caller that already
     * passed the products.import gate. Unknown and not-owned answer the same
     * 404 so the read path never leaks whether an id exists.
     */
    private function assertSubjectMayRead(ProductImport $import, ?string $subjectUserId): void
    {
        if ($subjectUserId === null || $import->created_by === null) {
            return;
        }

        if ((string) $import->created_by !== $subjectUserId) {
            throw new ResourceNotFound('Import session not found.');
        }
    }
}