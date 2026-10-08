<?php

namespace App\Actions\Conciliation;

use App\Enums\AuditAction;
use App\Enums\SessionStatus;
use App\Models\ImportAttempt;
use App\Models\ImportFile;
use App\Models\ReconciliationSession;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DeleteSession
{
    public function __construct(protected AuditRecorder $audit) {}

    /**
     * Permanently delete a session that was never processed, with its files and entries.
     *
     * @throws ActionRefusedException
     */
    public function handle(User $user, ReconciliationSession $session): void
    {
        Gate::forUser($user)->authorize('delete', $session);

        $storedFiles = DB::transaction(function () use ($user, $session): array {
            $session = ReconciliationSession::query()->lockForUpdate()->findOrFail($session->id);

            if ($session->status === SessionStatus::Processing) {
                throw new ActionRefusedException(__('conciliation.sessions.delete.processing'));
            }

            if ($session->hasEverBeenProcessed() || $session->status === SessionStatus::Processed) {
                throw new ActionRefusedException(__('conciliation.sessions.delete.processed'));
            }

            $files = $session->importFiles()->orderBy('id')->get();
            $attempts = $session->importAttempts()->get();

            $this->audit->record(
                $user,
                AuditAction::SessionDeleted,
                $session,
                $session->label(),
                [
                    'number' => $session->id,
                    'period' => $session->periodLabel(),
                    'status' => $session->status->value,
                    'files' => $files->map(fn (ImportFile $file): array => [
                        'slot' => $file->slot->value,
                        'file' => $file->original_name,
                        'status' => $file->status->value,
                    ])->all(),
                ],
                null,
            );

            $session->delete();

            return [
                ...$files->map(fn (ImportFile $file): array => [$file->disk, $file->path])->all(),
                ...$attempts->map(fn (ImportAttempt $attempt): array => [$attempt->disk, $attempt->path])->all(),
                ...$attempts->whereNotNull('error_report_path')->map(fn (ImportAttempt $attempt): array => [$attempt->disk, $attempt->error_report_path])->all(),
            ];
        });

        foreach ($storedFiles as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }
    }
}
