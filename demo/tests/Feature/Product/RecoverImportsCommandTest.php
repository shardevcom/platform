<?php

declare(strict_types=1);

namespace Tests\Feature\Product;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Shardevcom\Product\Jobs\PrepareProductImport;
use Shardevcom\Product\Models\ProductImport;
use Tests\TestCase;

/**
 * T-CASA-029 acceptance (resilience, bounded scope):
 *  - a pending session that never produced a chunk batch is SAFE to retry
 *    (re-dispatch of the pre-flight, no duplicate risk by construction);
 *  - a pending/processing session that may hold partial batch data is marked
 *    failed with explicit evidence, never left stuck forever;
 *  - --dry-run lists the recovery action and changes nothing;
 *  - fresh sessions (inside the stuck threshold) are never touched.
 */
final class RecoverImportsCommandTest extends TestCase
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

    public function test_dry_run_lists_the_recovery_action_without_changing_anything(): void
    {
        $this->makeImport('pending-fresh', 'pending', 0, now()->subMinute(5));
        $this->makeImport('pending-retry', 'pending', 0, now()->subHours(48));
        $this->makeImport('pending-ambiguous', 'pending', 10, now()->subHours(48));
        $this->makeImport('processing-stuck', 'processing', 10, now()->subHours(48));

        $this->artisan('shardev:imports:recover', ['--dry-run' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('pending-retry')
            ->expectsOutputToContain('pending-ambiguous')
            ->expectsOutputToContain('processing-stuck');

        // Nothing changed: not a single status moved.
        $this->assertDatabaseHas('product_imports', ['id' => 'pending-fresh', 'status' => 'pending']);
        $this->assertDatabaseHas('product_imports', ['id' => 'pending-retry', 'status' => 'pending']);
        $this->assertDatabaseHas('product_imports', ['id' => 'pending-ambiguous', 'status' => 'pending']);
        $this->assertDatabaseHas('product_imports', ['id' => 'processing-stuck', 'status' => 'processing']);
    }

    public function test_a_pending_session_without_rows_is_retried_by_redispatching_the_preflight(): void
    {
        Queue::fake();

        $this->makeImport('pending-retry', 'pending', 0, now()->subHours(48));

        $this->artisan('shardev:imports:recover')
            ->assertExitCode(0);

        $pushed = [];
        Queue::assertPushedOn('imports', PrepareProductImport::class, function ($job) use (&$pushed): bool {
            $pushed[] = $job;

            return true;
        });
        $this->assertCount(1, $pushed);

        // Retry does not pretend it finished: the session stays pending until
        // the re-dispatched pre-flight really completes.
        $this->assertDatabaseHas('product_imports', ['id' => 'pending-retry', 'status' => 'pending']);
    }

    public function test_sessions_that_may_hold_partial_data_are_failed_with_evidence(): void
    {
        $recovered = [];
        Log::listen(function (\Illuminate\Log\Events\MessageLogged $event) use (&$recovered): void {
            if ($event->level !== 'warning') {
                return;
            }
            if ($event->message === 'Import marked failed after being stuck past the recovery threshold.') {
                $recovered[] = ['import_id' => $event->context['import_id'], 'action' => $event->context['action']];
            }
        });

        $this->makeImport('pending-ambiguous', 'pending', 10, now()->subHours(48));
        $this->makeImport('processing-stuck', 'processing', 10, now()->subHours(48));
        $this->makeImport('processing-fresh', 'processing', 10, now()->subMinute(5));

        $this->artisan('shardev:imports:recover')
            ->assertExitCode(0)
            ->expectsOutputToContain('0 retried, 2 failed');

        // Stuck sessions became failed, with an explicit evidence summary.
        $this->assertDatabaseHas('product_imports', ['id' => 'pending-ambiguous', 'status' => 'failed']);
        $this->assertDatabaseHas('product_imports', ['id' => 'processing-stuck', 'status' => 'failed']);

        $ambiguous = ProductImport::findOrFail('pending-ambiguous');
        $this->assertStringContainsString('re-import to continue', $ambiguous->error_summary['message']);

        $stuck = ProductImport::findOrFail('processing-stuck');
        $this->assertStringContainsString('re-import to continue', $stuck->error_summary['message']);

        // Evidence landed on the platform log for every recovery decision.
        $this->assertContains(['import_id' => 'pending-ambiguous', 'action' => 'fail'], $recovered);
        $this->assertContains(['import_id' => 'processing-stuck', 'action' => 'fail'], $recovered);

        // Inside the threshold nothing moved.
        $this->assertDatabaseHas('product_imports', ['id' => 'processing-fresh', 'status' => 'processing']);
    }

    public function test_completed_and_failed_sessions_are_never_touched(): void
    {
        Queue::fake();

        $this->makeImport('completed-old', 'completed', 10, now()->subDays(10));
        $this->makeImport('failed-old', 'failed', 10, now()->subDays(10));

        $this->artisan('shardev:imports:recover')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
        $this->assertDatabaseHas('product_imports', ['id' => 'completed-old', 'status' => 'completed']);
        $this->assertDatabaseHas('product_imports', ['id' => 'failed-old', 'status' => 'failed']);
    }

    /**
     * @param Carbon|null $createdAt defaults to 48 hours ago
     */
    private function makeImport(
        string $id,
        string $status,
        int $totalRows,
        ?Carbon $createdAt = null,
    ): void {
        $createdAt ??= now()->subHours(48);

        ProductImport::create([
            'id' => $id,
            'file_path' => 'imports/'.$id.'.csv',
            'disk' => 'local',
            'format' => 'csv',
            'total_rows' => $totalRows,
            'status' => $status,
        ]);

        ProductImport::where('id', $id)->update([
            'created_at' => $createdAt->toDateTimeString(),
            'updated_at' => $createdAt->toDateTimeString(),
            'started_at' => $createdAt->toDateTimeString(),
        ]);
    }
}