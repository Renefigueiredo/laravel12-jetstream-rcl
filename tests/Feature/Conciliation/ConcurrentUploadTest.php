<?php

namespace Tests\Feature\Conciliation;

use App\Enums\ImportAttemptStatus;
use App\Enums\ImportFileStatus;
use App\Enums\ImportSlot;
use App\Jobs\PersistImportAttempt;
use App\Models\ImportAttempt;
use App\Models\ImportFile;
use App\Models\ReconciliationSession;
use App\Services\Import\SheetRows;
use App\Services\Import\SlotTakenException;
use App\Services\Import\SpreadsheetImporter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Concerns\SubmitsSpreadsheets;
use Tests\TestCase;

class ConcurrentUploadTest extends TestCase
{
    use RefreshDatabase;
    use SubmitsSpreadsheets;

    public function test_database_refuses_a_second_active_file_in_the_same_slot(): void
    {
        $session = ReconciliationSession::factory()->create();
        ImportFile::factory()->forSlot(ImportSlot::PaymentsSocial)->create(['reconciliation_session_id' => $session->id]);

        $this->expectException(UniqueConstraintViolationException::class);

        ImportFile::factory()->forSlot(ImportSlot::PaymentsSocial)->create(['reconciliation_session_id' => $session->id]);
    }

    public function test_replaced_files_and_other_slots_do_not_conflict(): void
    {
        $session = ReconciliationSession::factory()->create();

        ImportFile::factory()->forSlot(ImportSlot::PaymentsSocial)->replaced()->count(2)->create(['reconciliation_session_id' => $session->id]);
        ImportFile::factory()->forSlot(ImportSlot::PaymentsSocial)->create(['reconciliation_session_id' => $session->id]);
        ImportFile::factory()->forSlot(ImportSlot::PaymentsSaude)->create(['reconciliation_session_id' => $session->id]);

        $this->assertSame(2, ImportFile::query()->where('status', ImportFileStatus::Active)->count());
    }

    public function test_upload_that_loses_the_race_is_refused_without_leaving_files_behind(): void
    {
        $session = ReconciliationSession::factory()->create();
        $upload = $this->paymentsCsv($this->paymentRows(2), 'perdedor.csv');
        $path = $upload->storeAs('conciliation/incoming', 'perdedor.csv', ['disk' => 'local']);

        $attempt = ImportAttempt::factory()->withStatus(ImportAttemptStatus::Persisting)->create([
            'reconciliation_session_id' => $session->id,
            'user_id' => $session->created_by,
            'path' => $path,
            'rows_total' => 2,
        ]);

        $sheetRows = Mockery::mock(SheetRows::class);
        $sheetRows->shouldReceive('parsed')->andReturnUsing(function () use ($session) {
            ImportFile::query()->where('reconciliation_session_id', $session->id)->update(['status' => ImportFileStatus::Replaced]);
            ImportFile::factory()->forSlot(ImportSlot::PaymentsSocial)->create(['reconciliation_session_id' => $session->id, 'original_name' => 'vencedor.csv']);
            ImportFile::factory()->forSlot(ImportSlot::PaymentsSocial)->create(['reconciliation_session_id' => $session->id]);

            yield from [];
        });
        $this->app->instance(SheetRows::class, $sheetRows);

        try {
            app(SpreadsheetImporter::class)->import($attempt);
            $this->fail('The import should have been refused.');
        } catch (SlotTakenException $exception) {
            $this->assertSame(__('conciliation.errors.slot_taken'), $exception->getMessage());
        }

        $this->assertSame([], Storage::disk('local')->allFiles('conciliation/sessions'));
        $this->assertSame(0, ImportFile::query()->count());
    }

    public function test_job_reports_the_lost_race_as_a_refusal(): void
    {
        $session = ReconciliationSession::factory()->create();
        $attempt = ImportAttempt::factory()->withStatus(ImportAttemptStatus::Persisting)->create([
            'reconciliation_session_id' => $session->id,
            'user_id' => $session->created_by,
        ]);

        $importer = Mockery::mock(SpreadsheetImporter::class);
        $importer->shouldReceive('import')->andThrow(SlotTakenException::make());

        (new PersistImportAttempt($attempt->id))->handle($importer);

        $attempt->refresh();

        $this->assertSame(ImportAttemptStatus::Rejected, $attempt->status);
        $this->assertSame(__('conciliation.errors.slot_taken'), $attempt->message);
    }
}
