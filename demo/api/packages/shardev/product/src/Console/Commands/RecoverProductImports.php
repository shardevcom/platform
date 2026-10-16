<?php

declare(strict_types=1);

namespace Shardevcom\Product\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Shardevcom\Product\Jobs\PrepareProductImport;
use Shardevcom\Product\Models\ProductImport;

/**
 * T-CASA-029 — resilience: recover import sessions stuck in the queue.
 *
 * A worker can die between "session persisted" and "finalizer ran" (the
 * crash windows T-CASA-029 documents). Those sessions sit in 'pending'
 * (pre-flight never finished) or 'processing' (a chunk batch is, or was,
 * running). They must not rot forever: T-CASA-017 alerted on them, this
 * command ACTS on them once they are older than the stuck threshold.
 *
 * Recovery decision, per session older than platform.import.retention.stuck_hours:
 *
 *   - pending AND total_rows = 0  -> SAFE RETRY: the pre-flight never got far
 *     enough to dispatch a single chunk batch, so no product can exist from
 *     this session. Re-dispatch PrepareProductImport. This is idempotent by
 *     construction: as long as the session stays at total_rows=0 it holds no
 *     partial data, so it can be retried every run without duplicating rows.
 *   - pending with total_rows > 0 -> FAIL: the pre-flight counted rows and was
 *     about to dispatch the batch (or did and the worker died in between).
 *     The batch is not resumable (no persisted batch id) and may have created
 *     products, so an automatic retry could import rows twice. Mark failed
 *     with an explicit "re-import required" evidence and let ops decide.
 *   - processing                   -> FAIL: same ambiguity, mark failed with
 *     evidence instead of leaving the session stuck forever.
 *
 * Every action leaves evidence through the platform log (import_id, action,
 * scenario) and the session's error_summary. A recovery never leaves a stuck
 * session behind. A finalizer that was already in flight for a session we
 * declared failed may later flip the status back to the real terminal state
 * (completed/failed by row outcome): that last-writer-wins race is intended,
 * the alert/log always carry the recovery evidence.
 *
 * Schedule alongside the purge (backlog/fase-casa.md): hourly is enough for a
 * 24h threshold; --dry-run is the CI proof that it only acts on what the
 * policy says.
 */
final class RecoverProductImports extends Command
{
    protected $signature = 'shardev:imports:recover
                            {--dry-run : List stuck imports and the recovery action without doing it.}
                            {--stuck-hours= : Override the stuck retention threshold in hours.}';

    protected $description = 'Retry or fail import sessions stuck in pending/processing past the stuck threshold.';

    public function handle(): int
    {
        $stuckHours = (int) ($this->option('stuck-hours') ?: config('platform.import.retention.stuck_hours', 24));
        $cutoff = now()->subHours($stuckHours);

        $stuck = ProductImport::whereIn('status', ['pending', 'processing'])
            ->where('created_at', '<', $cutoff)
            ->get();

        $rows = $stuck->map(static fn (ProductImport $import): array => [
            'import_id' => $import->id,
            'status' => $import->status,
            'total_rows' => $import->total_rows,
            'action' => $import->status === 'pending' && (int) $import->total_rows === 0 ? 'retry' : 'fail',
            'created_at' => optional($import->created_at)->toDateTimeString(),
        ])->all();

        if ($this->option('dry-run') || $stuck->isNotEmpty()) {
            $this->report($rows, $stuckHours, $stuck->isEmpty());
        }

        if ($this->option('dry-run')) {
            $this->info('DRY RUN — nothing was changed.');

            return self::SUCCESS;
        }

        $retried = 0;
        $failed = 0;

        foreach ($stuck as $import) {
            if ($import->status === 'pending' && (int) $import->total_rows === 0) {
                PrepareProductImport::dispatch($import->id);

                Log::warning('Import recovered by re-dispatching the queue pre-flight.', [
                    'import_id' => $import->id,
                    'status' => $import->status,
                    'total_rows' => $import->total_rows,
                    'action' => 'retry',
                ]);

                $retried++;
                continue;
            }

            $import->markFailed(
                'Worker died while the import was '.$import->status.' (stuck past '.$stuckHours.'h). '
                .'No automatic retry: the batch may hold partial data, re-import to continue.',
            );

            Log::warning('Import marked failed after being stuck past the recovery threshold.', [
                'import_id' => $import->id,
                'status' => $import->status,
                'total_rows' => $import->total_rows,
                'action' => 'fail',
            ]);

            $failed++;
        }

        $this->info("Recovered {$stuck->count()} stuck import session(s): {$retried} retried, {$failed} failed.");

        return self::SUCCESS;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function report(array $rows, int $stuckHours, bool $empty): void
    {
        if ($rows !== []) {
            $this->table(
                ['import_id', 'status', 'total_rows', 'action', 'created_at'],
                array_map(
                    static fn (array $row): array => [
                        $row['import_id'],
                        $row['status'],
                        (string) $row['total_rows'],
                        $row['action'],
                        $row['created_at'],
                    ],
                    $rows,
                ),
            );
        }

        if ($empty) {
            $this->info('Nothing stuck past the '.$stuckHours.'h threshold.');
        }
    }
}