<?php

namespace App\Services\Import;

use App\Contracts\SpreadsheetLayout;
use App\Enums\AuditAction;
use App\Enums\ImportFileStatus;
use App\Enums\SpreadsheetLayoutType;
use App\Models\AuthorizationEntry;
use App\Models\ImportAttempt;
use App\Models\ImportFile;
use App\Models\PaymentEntry;
use App\Models\ReconciliationSession;
use App\Services\Audit\AuditRecorder;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SpreadsheetImporter
{
    public function __construct(
        protected SheetRows $sheetRows,
        protected LayoutRegistry $layouts,
        protected AuditRecorder $audit,
    ) {}

    /**
     * Second pass over a validated file: persist the file and its entries, all or nothing.
     *
     * @param  callable(int $rowsRead): void|null  $onProgress
     *
     * @throws SessionLockedException
     * @throws SlotTakenException
     */
    public function import(ImportAttempt $attempt, ?callable $onProgress = null): ImportFile
    {
        $layout = $this->layouts->for($attempt->slot->layout());
        $disk = Storage::disk($attempt->disk);
        $definitivePath = $this->definitivePath($attempt);

        $disk->copy($attempt->path, $definitivePath);

        try {
            $importFile = DB::transaction(fn (): ImportFile => $this->persist($attempt, $layout, $definitivePath, $onProgress));
        } catch (UniqueConstraintViolationException) {
            $disk->delete($definitivePath);

            throw SlotTakenException::make();
        } catch (Throwable $exception) {
            $disk->delete($definitivePath);

            throw $exception;
        }

        $attempt->deleteReceivedFile();

        return $importFile;
    }

    /**
     * @param  callable(int $rowsRead): void|null  $onProgress
     */
    protected function persist(ImportAttempt $attempt, SpreadsheetLayout $layout, string $definitivePath, ?callable $onProgress): ImportFile
    {
        $session = ReconciliationSession::query()->lockForUpdate()->findOrFail($attempt->reconciliation_session_id);

        if (! $session->isOpen()) {
            throw SessionLockedException::for($session);
        }

        $previousFile = $session->activeFiles()->where('slot', $attempt->slot)->lockForUpdate()->first();

        $previousFile?->update(['status' => ImportFileStatus::Replaced, 'replaced_at' => now()]);

        $importFile = ImportFile::query()->create([
            'reconciliation_session_id' => $session->id,
            'slot' => $attempt->slot,
            'status' => ImportFileStatus::Active,
            'original_name' => $attempt->original_name,
            'disk' => $attempt->disk,
            'path' => $definitivePath,
            'size_bytes' => $attempt->size_bytes,
            'sha256' => $attempt->sha256,
            'sheet_count' => $attempt->sheet_count ?? 1,
            'missing_columns' => $attempt->missing_columns ?: null,
            'period_divergence' => $attempt->divergence_confirmed_by !== null,
            'divergence_confirmed_by' => $attempt->divergence_confirmed_by,
            'divergence_confirmed_at' => $attempt->divergence_confirmed_at,
            'uploaded_by' => $attempt->user_id,
        ]);

        $counts = $this->insertEntries($attempt, $layout, $session, $importFile, $onProgress);

        $importFile->update($counts);

        if ($previousFile !== null) {
            $this->audit->record(
                $attempt->user,
                AuditAction::FileReplaced,
                $session,
                $session->label(),
                $this->fileSnapshot($previousFile),
                $this->fileSnapshot($importFile),
            );
        }

        return $importFile;
    }

    /**
     * @param  callable(int $rowsRead): void|null  $onProgress
     * @return array<string, mixed>
     */
    protected function insertEntries(ImportAttempt $attempt, SpreadsheetLayout $layout, ReconciliationSession $session, ImportFile $importFile, ?callable $onProgress): array
    {
        $model = $this->entryModel($layout->type());
        $existing = $this->existingCounts($model, $session, $attempt);
        $chunkSize = max(1, (int) config('conciliation.upload.insert_chunk'));
        $period = $session->period->format('Y-m');
        $createdAt = now()->toDateTimeString();

        $result = new ValidationResult;
        $imported = 0;
        $skippedExisting = 0;
        $buffer = [];

        foreach ($this->sheetRows->parsed($attempt->absolutePath(), $layout, $attempt->slot) as $parsed) {
            $result->rowsTotal++;

            if ($result->rowsTotal % 1000 === 0 && $onProgress !== null) {
                $onProgress($result->rowsTotal);
            }

            if ($parsed === null) {
                $result->rowsSkippedValue++;

                continue;
            }

            if (is_array($parsed)) {
                throw new RuntimeException('The file changed between validation and import.');
            }

            $result->registerDate($parsed->periodDate);

            if ($parsed->periodDate->format('Y-m') !== $period) {
                $result->rowsOutOfPeriod++;
            }

            if (($existing[$parsed->identityKey] ?? 0) > 0) {
                $existing[$parsed->identityKey]--;
                $skippedExisting++;

                continue;
            }

            $buffer[] = $this->entryRow($parsed, $importFile, $createdAt);
            $imported++;

            if (count($buffer) >= $chunkSize) {
                $model::query()->insert($buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            $model::query()->insert($buffer);
        }

        return [
            'rows_imported' => $imported,
            'rows_skipped_value' => $result->rowsSkippedValue,
            'rows_skipped_existing' => $skippedExisting,
            'rows_out_of_period' => $result->rowsOutOfPeriod,
            'min_date' => $result->minDate,
            'max_date' => $result->maxDate,
        ];
    }

    /**
     * Count, per identity key, the active entries of the other sessions of the same period.
     *
     * @param  class-string<Model>  $model
     * @return array<string, int>
     */
    protected function existingCounts(string $model, ReconciliationSession $session, ImportAttempt $attempt): array
    {
        $table = (new $model)->getTable();

        return $model::query()
            ->join('import_files', 'import_files.id', '=', $table.'.import_file_id')
            ->join('reconciliation_sessions', 'reconciliation_sessions.id', '=', $table.'.reconciliation_session_id')
            ->where('import_files.status', ImportFileStatus::Active->value)
            ->whereDate('reconciliation_sessions.period', $session->period)
            ->where('reconciliation_sessions.id', '!=', $session->id)
            ->when($attempt->slot->unit() !== null, fn ($query) => $query->where($table.'.unit', $attempt->slot->unit()->value))
            ->groupBy($table.'.identity_key')
            ->selectRaw($table.'.identity_key, count(*) as aggregate')
            ->toBase()
            ->pluck('aggregate', 'identity_key')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function entryRow(ParsedRow $parsed, ImportFile $importFile, string $createdAt): array
    {
        $attributes = array_map(
            fn (mixed $value): mixed => $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value,
            $parsed->attributes,
        );

        return [
            ...$attributes,
            'import_file_id' => $importFile->id,
            'reconciliation_session_id' => $importFile->reconciliation_session_id,
            'row_number' => $parsed->rowNumber,
            'identity_key' => $parsed->identityKey,
            'raw' => json_encode($parsed->raw, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'created_at' => $createdAt,
        ];
    }

    /**
     * @return class-string<Model>
     */
    protected function entryModel(SpreadsheetLayoutType $type): string
    {
        return match ($type) {
            SpreadsheetLayoutType::Authorizations => AuthorizationEntry::class,
            SpreadsheetLayoutType::Payments => PaymentEntry::class,
        };
    }

    protected function definitivePath(ImportAttempt $attempt): string
    {
        $extension = strtolower(pathinfo($attempt->path, PATHINFO_EXTENSION));

        return sprintf('conciliation/sessions/%d/%s/%s.%s', $attempt->reconciliation_session_id, $attempt->slot->value, Str::uuid(), $extension);
    }

    /**
     * @return array<string, mixed>
     */
    protected function fileSnapshot(ImportFile $file): array
    {
        return [
            'slot' => $file->slot->value,
            'import_file_id' => $file->id,
            'file' => $file->original_name,
            'entries' => $file->rows_imported,
        ];
    }
}
