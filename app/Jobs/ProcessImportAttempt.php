<?php

namespace App\Jobs;

use App\Enums\ImportAttemptStatus;
use App\Models\ImportAttempt;
use App\Services\Import\ErrorReportWriter;
use App\Services\Import\LayoutRegistry;
use App\Services\Import\SpreadsheetImporter;
use App\Services\Import\SpreadsheetValidator;
use App\Services\Import\ValidationResult;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessImportAttempt implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $importAttemptId) {}

    /**
     * Validate the whole file and, when nothing needs confirmation, persist it.
     */
    public function handle(SpreadsheetValidator $validator, LayoutRegistry $layouts): void
    {
        $claimed = ImportAttempt::query()
            ->whereKey($this->importAttemptId)
            ->where('status', ImportAttemptStatus::Queued)
            ->update(['status' => ImportAttemptStatus::Validating, 'progress' => 10]);

        if ($claimed === 0) {
            return;
        }

        $attempt = ImportAttempt::query()->with('session')->findOrFail($this->importAttemptId);

        $reportPath = 'conciliation/reports/'.Str::uuid().'.xlsx';
        $errorReport = new ErrorReportWriter(Storage::disk($attempt->disk)->path($reportPath));

        $result = $validator->validate(
            $attempt->absolutePath(),
            $layouts->for($attempt->slot->layout()),
            $attempt->slot,
            $attempt->session->period,
            $errorReport,
        );

        $attempt->fill($this->validationAttributes($result));

        if ($result->isRejected()) {
            $attempt->fill([
                'status' => ImportAttemptStatus::Rejected,
                'progress' => 100,
                'error_report_path' => $errorReport->hasErrors() ? $reportPath : null,
            ])->save();

            $attempt->deleteReceivedFile();

            return;
        }

        if ($result->hasPeriodDivergence()) {
            $attempt->fill(['status' => ImportAttemptStatus::AwaitingConfirmation, 'progress' => 50])->save();

            return;
        }

        $attempt->fill(['status' => ImportAttemptStatus::Persisting, 'progress' => 50])->save();

        (new PersistImportAttempt($attempt->id))->handle(app(SpreadsheetImporter::class));
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

    /**
     * @return array<string, mixed>
     */
    protected function validationAttributes(ValidationResult $result): array
    {
        return [
            'sheet_count' => $result->sheetCount,
            'missing_columns' => $result->missingColumns ?: null,
            'rows_total' => $result->rowsTotal,
            'rows_valid' => $result->rowsValid,
            'rows_skipped_value' => $result->rowsSkippedValue,
            'rows_out_of_period' => $result->rowsOutOfPeriod,
            'min_date' => $result->minDate,
            'max_date' => $result->maxDate,
            'error_count' => $result->errorCount,
            'first_errors' => $result->firstErrors ?: null,
            'message' => $result->message,
        ];
    }
}
