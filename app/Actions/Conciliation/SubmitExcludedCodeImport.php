<?php

namespace App\Actions\Conciliation;

use App\Enums\ExcludedCodeImportStatus;
use App\Jobs\ImportExcludedCodes;
use App\Models\ExcludedCodeImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class SubmitExcludedCodeImport
{
    /**
     * @var list<string>
     */
    protected const ACCEPTED_EXTENSIONS = ['csv', 'xlsx'];

    /**
     * Receive a spreadsheet of operation codes and queue its import.
     *
     * @throws ActionRefusedException
     */
    public function handle(User $user, UploadedFile $file): ExcludedCodeImport
    {
        Gate::forUser($user)->authorize('manage-excluded-codes');

        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ACCEPTED_EXTENSIONS, true)) {
            throw new ActionRefusedException(__('conciliation.excluded_codes.import.file_type'));
        }

        $maxSizeMb = (int) config('conciliation.excluded_codes.max_size_mb');

        if ($file->getSize() > $maxSizeMb * 1024 * 1024) {
            throw new ActionRefusedException(__('conciliation.errors.file_size', ['max' => $maxSizeMb]));
        }

        $alreadyRunning = ExcludedCodeImport::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ExcludedCodeImportStatus::inProgress())
            ->exists();

        if ($alreadyRunning) {
            throw new ActionRefusedException(__('conciliation.excluded_codes.import.already_running'));
        }

        $disk = (string) config('conciliation.upload.disk');
        $path = $file->storeAs('conciliation/excluded-codes', Str::uuid().'.'.$extension, ['disk' => $disk]);

        $import = ExcludedCodeImport::query()->create([
            'user_id' => $user->id,
            'status' => ExcludedCodeImportStatus::Queued,
            'original_name' => $file->getClientOriginalName(),
            'disk' => $disk,
            'path' => $path,
            'size_bytes' => $file->getSize(),
        ]);

        ImportExcludedCodes::dispatch($import->id);

        return $import;
    }
}
