<?php

declare(strict_types=1);

namespace Tests\Feature\Product;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Shardevcom\Product\Models\ProductImport;
use Shardevcom\Product\Models\ProductImportRow;
use Tests\TestCase;

/**
 * T-CASA-017 acceptance:
 *  - the purge command with --dry-run lists what would be deleted and why;
 *  - past the retention window the data (sessions, traced rows and the stored
 *    file) is really deleted; inside the window nothing is touched;
 *  - a session stuck in pending/processing beyond the threshold is alerted
 *    and never silently destroyed.
 */
final class PurgeImportsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        config(['platform.import.retention' => [
            'completed' => 90,
            'failed' => 30,
            'stuck_hours' => 24,
        ]]);
    }

    public function test_dry_run_lists_what_would_be_purged_and_why_without_deleting(): void
    {
        $this->makeSession('old-completed', 'completed', now()->subDays(100));
        $this->makeSession('recent-completed', 'completed', now()->subDays(10));
        $this->makeSession('old-failed', 'failed', now()->subDays(40));
        $this->makeSession('recent-failed', 'failed', now()->subDays(5));

        $this->artisan('shardev:imports:purge', ['--dry-run' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('old-completed')
            ->expectsOutputToContain('old-failed');

        // Nothing was deleted: sessions, traced rows and files all survive.
        $this->assertDatabaseHas('product_imports', ['id' => 'old-completed']);
        $this->assertDatabaseHas('product_imports', ['id' => 'recent-completed']);
        $this->assertDatabaseHas('product_imports', ['id' => 'old-failed']);
        $this->assertDatabaseHas('product_imports', ['id' => 'recent-failed']);
        $this->assertDatabaseHas('product_import_rows', ['import_id' => 'old-completed']);
        $this->assertTrue(Storage::disk('local')->exists('imports/old-completed.csv'));
    }

    public function test_purge_deletes_past_the_window_and_keeps_everything_inside_it(): void
    {
        $this->makeSession('old-completed', 'completed', now()->subDays(100));
        $this->makeSession('recent-completed', 'completed', now()->subDays(10));
        $this->makeSession('old-failed', 'failed', now()->subDays(40));
        $this->makeSession('recent-failed', 'failed', now()->subDays(5));

        $this->artisan('shardev:imports:purge')
            ->assertExitCode(0)
            ->expectsOutputToContain('Purged 2 expired import session(s).');

        // Expired sessions, their traced rows (FK cascade) and their stored
        // files are gone.
        $this->assertDatabaseMissing('product_imports', ['id' => 'old-completed']);
        $this->assertDatabaseMissing('product_imports', ['id' => 'old-failed']);
        $this->assertDatabaseMissing('product_import_rows', ['import_id' => 'old-completed']);
        $this->assertDatabaseMissing('product_import_rows', ['import_id' => 'old-failed']);
        $this->assertFalse(Storage::disk('local')->exists('imports/old-completed.csv'));
        $this->assertFalse(Storage::disk('local')->exists('imports/old-failed.csv'));

        // Inside the window nothing was touched.
        $this->assertDatabaseHas('product_imports', ['id' => 'recent-completed']);
        $this->assertDatabaseHas('product_imports', ['id' => 'recent-failed']);
        $this->assertDatabaseHas('product_import_rows', ['import_id' => 'recent-completed']);
        $this->assertTrue(Storage::disk('local')->exists('imports/recent-completed.csv'));
    }

    public function test_completed_with_errors_follows_the_completed_retention(): void
    {
        $this->makeSession('old-errors', 'completed_with_errors', now()->subDays(100));
        $this->makeSession('recent-errors', 'completed_with_errors', now()->subDays(10));

        $this->artisan('shardev:imports:purge')
            ->assertExitCode(0)
            ->expectsOutputToContain('Purged 1 expired import session(s).');

        $this->assertDatabaseMissing('product_imports', ['id' => 'old-errors']);
        $this->assertDatabaseHas('product_imports', ['id' => 'recent-errors']);
    }

    public function test_a_stuck_import_beyond_the_threshold_is_alerted_and_never_purged(): void
    {
        $alerts = [];
        Log::listen(function (\Illuminate\Log\Events\MessageLogged $event) use (&$alerts): void {
            if ($event->level === 'warning') {
                $alerts[] = ['message' => $event->message, 'import_id' => $event->context['import_id'] ?? null];
            }
        });

        $this->makeSession('stuck-processing', 'processing', null, now()->subHours(48));
        $this->makeSession('fresh-processing', 'processing', null, now()->subMinute(5));

        $this->artisan('shardev:imports:purge', ['--dry-run' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('stuck-processing');

        // The platform log carries the alert payload.
        $this->assertContains(
            ['message' => 'Import stuck beyond the alert threshold.', 'import_id' => 'stuck-processing'],
            $alerts,
        );

        // The stuck session is never destroyed silently.
        $this->assertDatabaseHas('product_imports', ['id' => 'stuck-processing']);
        $this->assertDatabaseHas('product_imports', ['id' => 'fresh-processing']);
        $this->assertTrue(Storage::disk('local')->exists('imports/stuck-processing.csv'));
    }

    /**
     * @param Carbon|null $completedAt null => status stays open (stuck simulation)
     * @param Carbon|null $createdAt defaults to 120 days ago
     */
    private function makeSession(
        string $id,
        string $status,
        ?Carbon $completedAt = null,
        ?Carbon $createdAt = null,
    ): void {
        $createdAt ??= now()->subDays(120);

        Storage::put('imports/'.$id.'.csv', "sku,name\n{$id},Demo\n");

        ProductImport::create([
            'id' => $id,
            'file_path' => 'imports/'.$id.'.csv',
            'disk' => 'local',
            'format' => 'csv',
            'total_rows' => 1,
            'status' => $status,
        ]);

        ProductImportRow::create([
            'import_id' => $id,
            'row_number' => 2,
            'data' => ['sku' => $id, 'name' => 'Demo'],
            'status' => $status === 'failed' ? 'failed' : 'success',
        ]);

        ProductImport::where('id', $id)->update([
            'created_at' => $createdAt->toDateTimeString(),
            'updated_at' => $createdAt->toDateTimeString(),
            'started_at' => $createdAt->toDateTimeString(),
            'completed_at' => $completedAt?->toDateTimeString(),
        ]);
    }
}