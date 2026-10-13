<?php

declare(strict_types=1);

namespace Shardevcom\Product\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Shardevcom\Product\Models\ProductImport;

/**
 * T-CASA-017 — retention and purge for import sessions.
 *
 * product_imports / product_import_rows keep the original row data and the
 * failure detail: that is potential PII and must not live forever. Default
 * policy (configurable under platform.import.retention):
 *
 *   - completed / completed_with_errors -> 90 days
 *   - failed                           -> 30 days
 *   - pending / processing older than stuck_hours -> alert only (no delete)
 *
 * Deleting a session removes its rows (FK cascade) and the STORED file too,
 * so the raw upload does not outlive its traceability window. A session that
 * never finished is never silently destroyed: it is flagged by the alert so
 * a human can decide (T-CASA-029 owns the recovery side). The file delete is
 * best-effort: an orphan on storage must not fail the purge of the database
 * records.
 *
 * Scheduled daily (documented in backlog/fase-casa.md); --dry-run is the CI
 * proof that the command only deletes what the policy says.
 */
final class PurgeProductImports extends Command
{
    protected $signature = 'shardev:imports:purge
                            {--dry-run : List what would be purged and why, without deleting.}
                            {--stuck-hours= : Override the stuck alert threshold in hours.}';

    protected $description = 'Purge import sessions past their retention window and alert on stuck imports.';

    public function handle(): int
    {
        $now = now();
        $stuckHours = (int) ($this->option('stuck-hours') ?: config('platform.import.retention.stuck_hours', 24));
        $retention = [
            'completed' => (int) config('platform.import.retention.completed', 90),
            'completed_with_errors' => (int) config('platform.import.retention.completed', 90),
            'failed' => (int) config('platform.import.retention.failed', 30),
        ];

        $expired = [];

        foreach (ProductImport::all() as $import) {
            $days = $retention[$import->status] ?? null;
            $reference = $import->completed_at ?? $import->created_at;

            if ($days === null || $reference === null || ! $reference->isBefore($now->copy()->subDays($days))) {
                continue;
            }

            $expired[] = [
                'import_id' => $import->id,
                'status' => $import->status,
                'reference' => $reference->toDateTimeString(),
                'retention' => "{$days}d",
                'rows' => $import->rows()->count(),
                'reason' => "{$days}d retention exceeded (".$reference->diffForHumans($now).')',
                'disk' => $import->disk,
                'file_path' => $import->file_path,
            ];
        }

        $stuck = ProductImport::whereIn('status', ['pending', 'processing'])
            ->where('created_at', '<', $now->copy()->subHours($stuckHours))
            ->get();

        if ($this->option('dry-run') || $expired !== [] || $stuck->isNotEmpty()) {
            $this->report($expired, $stuck, $stuckHours);
        }

        if ($this->option('dry-run')) {
            $this->info('DRY RUN — nothing was deleted.');

            return self::SUCCESS;
        }

        foreach ($expired as $entry) {
            Storage::disk($entry['disk'])->delete($entry['file_path']);
            ProductImport::find($entry['import_id'])?->delete();
        }

        $this->info('Purged '.count($expired).' expired import session(s).');

        return self::SUCCESS;
    }

    /**
     * @param list<array<string, mixed>> $expired
     */
    private function report(array $expired, \Illuminate\Support\Collection $stuck, int $stuckHours): void
    {
        if ($expired !== []) {
            $this->table(
                ['import_id', 'status', 'reference', 'retention', 'rows', 'reason'],
                array_map(
                    static fn (array $row): array => [
                        $row['import_id'],
                        $row['status'],
                        $row['reference'],
                        $row['retention'],
                        (string) $row['rows'],
                        $row['reason'],
                    ],
                    $expired,
                ),
            );
        }

        foreach ($stuck as $import) {
            Log::warning('Import stuck beyond the alert threshold.', [
                'import_id' => $import->id,
                'status' => $import->status,
                'created_at' => optional($import->created_at)->toDateTimeString(),
            ]);

            $this->warn(sprintf(
                'ALERT: import %s (%s) stuck beyond %dh — not purged.',
                $import->id,
                $import->status,
                $stuckHours,
            ));
        }

        if ($expired === [] && $stuck->isEmpty()) {
            $this->info('Nothing expired or stuck.');
        }
    }
}