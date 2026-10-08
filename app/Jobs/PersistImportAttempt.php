<?php

namespace App\Jobs;

use App\Enums\ImportAttemptStatus;
use App\Models\ImportAttempt;
use App\Services\Import\SessionLockedException;
use App\Services\Import\SlotTakenException;
use App\Services\Import\SpreadsheetImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PersistImportAttempt implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $importAttemptId) {}

    /**
     * Persist a validated file and its entries, all or nothing.
     */
    public function handle(SpreadsheetImporter $importer): void
    {
        $attempt = ImportAttempt::query()->with(['session', 'user'])->find($this->importAttemptId);

        if ($attempt === null || $attempt->status !== ImportAttemptStatus::Persisting) {
            return;
        }

        $rowsExpected = max(1, (int) $attempt->rows_total);

        try {
            $importFile = $importer->import($attempt, function (int $rowsRead) use ($attempt, $rowsExpected): void {
                ImportAttempt::query()->whereKey($attempt->id)->update([
                    'progress' => min(95, 50 + (int) floor($rowsRead / $rowsExpected * 45)),
                ]);
            });
        } catch (SessionLockedException|SlotTakenException $exception) {
            $attempt->update(['status' => ImportAttemptStatus::Rejected, 'progress' => 100, 'message' => $exception->getMessage()]);
            $attempt->deleteReceivedFile();

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->failed($exception);

            return;
        }

        $attempt->update(['status' => ImportAttemptStatus::Accepted, 'progress' => 100, 'import_file_id' => $importFile->id]);
    }

    /**
     * Any unexpected failure leaves no file and no entry behind.
     */
    public function failed(?Throwable $exception): void
    {
        $attempt = ImportAttempt::query()->find($this->importAttemptId);

        if ($attempt === null || $attempt->status->isFinished()) {
            return;
        }

        $attempt->update(['status' => ImportAttemptStatus::Failed, 'message' => __('conciliation.import.failed')]);
        $attempt->deleteReceivedFile();
    }
}
