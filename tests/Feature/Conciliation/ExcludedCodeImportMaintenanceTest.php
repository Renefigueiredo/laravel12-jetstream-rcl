<?php

namespace Tests\Feature\Conciliation;

use App\Enums\ExcludedCodeImportStatus;
use App\Models\ExcludedCodeImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExcludedCodeImportMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    protected function storedImport(string $state, string $updatedAt): ExcludedCodeImport
    {
        $import = ExcludedCodeImport::factory()->{$state}()->create();

        Storage::disk('local')->put($import->path, "20150652\r\n");

        ExcludedCodeImport::query()->whereKey($import->id)->update(['updated_at' => $updatedAt]);

        return $import;
    }

    public function test_old_rejected_and_failed_imports_are_removed_with_their_files(): void
    {
        $this->travelTo('2026-07-10 12:00:00');
        config(['conciliation.attempts.retention_hours' => 24]);

        $oldRejected = $this->storedImport('rejected', '2026-07-09 11:00:00');
        $oldFailed = $this->storedImport('failed', '2026-07-09 11:00:00');
        $recentRejected = $this->storedImport('rejected', '2026-07-09 13:00:00');
        $oldCompleted = $this->storedImport('completed', '2026-06-01 00:00:00');

        $this->artisan('conciliation:prune-import-attempts')->assertSuccessful();

        $this->assertModelMissing($oldRejected);
        $this->assertModelMissing($oldFailed);
        Storage::disk('local')->assertMissing($oldRejected->path);
        Storage::disk('local')->assertMissing($oldFailed->path);

        $this->assertModelExists($recentRejected);
        $this->assertModelExists($oldCompleted);
        Storage::disk('local')->assertExists($recentRejected->path);
        Storage::disk('local')->assertExists($oldCompleted->path);
    }

    public function test_stalled_imports_are_marked_as_failed(): void
    {
        $this->travelTo('2026-07-10 12:00:00');
        config(['conciliation.stale.attempt_minutes' => 30]);

        $stalledQueued = $this->storedImport('queued', '2026-07-10 11:29:00');
        $stalledProcessing = $this->storedImport('processing', '2026-07-10 11:29:00');
        $running = $this->storedImport('processing', '2026-07-10 11:31:00');
        $completed = $this->storedImport('completed', '2026-07-10 10:00:00');

        $this->artisan('conciliation:prune-import-attempts')->assertSuccessful();

        foreach ([$stalledQueued, $stalledProcessing] as $import) {
            $import->refresh();

            $this->assertSame(ExcludedCodeImportStatus::Failed, $import->status);
            $this->assertSame(__('conciliation.excluded_codes.import.stalled'), $import->failure_message);
        }

        $this->assertSame(ExcludedCodeImportStatus::Processing, $running->refresh()->status);
        $this->assertSame(ExcludedCodeImportStatus::Completed, $completed->refresh()->status);
    }

    public function test_a_stalled_import_no_longer_blocks_the_user(): void
    {
        $this->travelTo('2026-07-10 12:00:00');

        $stalled = $this->storedImport('processing', '2026-07-10 10:00:00');

        $this->artisan('conciliation:prune-import-attempts')->assertSuccessful();

        $this->assertFalse($stalled->refresh()->status->isInProgress());
    }
}
