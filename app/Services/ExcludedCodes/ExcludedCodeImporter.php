<?php

namespace App\Services\ExcludedCodes;

use App\Enums\AuditAction;
use App\Enums\ExcludedCodeImportStatus;
use App\Enums\ExcludedCodeSource;
use App\Models\ExcludedCodeImport;
use App\Models\ExcludedOperationCode;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

class ExcludedCodeImporter
{
    public function __construct(protected AuditRecorder $audit) {}

    /**
     * Add the codes of an accepted file to the list; codes already there are left as they are.
     */
    public function import(ExcludedCodeImport $import, ParsedExcludedCodeFile $parsed): void
    {
        DB::transaction(function () use ($import, $parsed): void {
            $createdAt = now();

            $rows = array_map(fn (array $code): array => [
                'code' => $code['code'],
                'description' => $code['description'],
                'source' => ExcludedCodeSource::File->value,
                'excluded_code_import_id' => $import->id,
                'created_by' => $import->user_id,
                'created_at' => $createdAt,
            ], $parsed->codes);

            foreach (array_chunk($rows, (int) config('conciliation.upload.insert_chunk')) as $chunk) {
                ExcludedOperationCode::query()->insertOrIgnore($chunk);
            }

            $addedCodes = $import->codes()->orderBy('code')->pluck('code')->all();
            $added = count($addedCodes);
            $ignored = count($parsed->codes) + $parsed->repeated - $added;

            $import->update([
                'status' => ExcludedCodeImportStatus::Completed,
                'added_count' => $added,
                'ignored_count' => $ignored,
                'finished_at' => now(),
            ]);

            $this->audit->record(
                $import->user,
                AuditAction::ExcludedCodesImported,
                $import,
                $import->original_name,
                null,
                ['file' => $import->original_name, 'added' => $added, 'ignored' => $ignored, 'codes' => $addedCodes],
            );
        });
    }
}
