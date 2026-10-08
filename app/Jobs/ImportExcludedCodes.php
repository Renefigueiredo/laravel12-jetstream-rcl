<?php

namespace App\Jobs;

use App\Contracts\SpreadsheetReader;
use App\Enums\ExcludedCodeImportStatus;
use App\Models\ExcludedCodeImport;
use App\Services\ExcludedCodes\ExcludedCodeFileParser;
use App\Services\ExcludedCodes\ExcludedCodeImporter;
use App\Services\ExcludedCodes\ParsedExcludedCodeFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ImportExcludedCodes implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $excludedCodeImportId) {}

    /**
     * Validate the whole file and, when every row is valid, add the new codes to the list.
     */
    public function handle(SpreadsheetReader $reader, ExcludedCodeFileParser $parser, ExcludedCodeImporter $importer): void
    {
        $claimed = ExcludedCodeImport::query()
            ->whereKey($this->excludedCodeImportId)
            ->where('status', ExcludedCodeImportStatus::Queued)
            ->update(['status' => ExcludedCodeImportStatus::Processing, 'started_at' => now(), 'updated_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $import = ExcludedCodeImport::query()->with('user')->findOrFail($this->excludedCodeImportId);

        $parsed = $this->read($reader, $parser, $import);

        if (! $parsed->isAccepted()) {
            $import->update([
                'status' => ExcludedCodeImportStatus::Rejected,
                'errors' => $parsed->errors ?: null,
                'failure_message' => $parsed->refusal,
                'finished_at' => now(),
            ]);

            return;
        }

        $importer->import($import, $parsed);
    }

    /**
     * Any unexpected failure leaves the list as it was.
     */
    public function failed(?Throwable $exception): void
    {
        ExcludedCodeImport::query()
            ->whereKey($this->excludedCodeImportId)
            ->whereIn('status', ExcludedCodeImportStatus::inProgress())
            ->update([
                'status' => ExcludedCodeImportStatus::Failed,
                'failure_message' => __('conciliation.excluded_codes.import.failed'),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * A file that cannot be read is refused, not treated as a failure of the job.
     */
    protected function read(SpreadsheetReader $reader, ExcludedCodeFileParser $parser, ExcludedCodeImport $import): ParsedExcludedCodeFile
    {
        try {
            return $parser->parse($reader->rows($import->absolutePath()));
        } catch (Throwable) {
            return ParsedExcludedCodeFile::refused(__('conciliation.errors.unreadable'));
        }
    }
}
