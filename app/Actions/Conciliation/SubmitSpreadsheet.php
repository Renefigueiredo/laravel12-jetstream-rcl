<?php

namespace App\Actions\Conciliation;

use App\Enums\ImportAttemptStatus;
use App\Enums\ImportSlot;
use App\Jobs\ProcessImportAttempt;
use App\Models\ImportAttempt;
use App\Models\ReconciliationSession;
use App\Models\User;
use App\Services\Import\SessionLockedException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class SubmitSpreadsheet
{
    /**
     * @var list<string>
     */
    protected const ACCEPTED_EXTENSIONS = ['xlsx', 'csv'];

    public function __construct(protected CancelImportAttempt $cancelImportAttempt) {}

    /**
     * Receive a spreadsheet for a slot and queue its validation.
     *
     * Returns null when the same file is already loaded in the slot.
     *
     * @throws ActionRefusedException
     */
    public function handle(User $user, ReconciliationSession $session, ImportSlot $slot, UploadedFile $file): ?ImportAttempt
    {
        Gate::forUser($user)->authorize('upload', $session);

        $session = ReconciliationSession::query()->findOrFail($session->id);

        if (! $session->isOpen()) {
            throw new ActionRefusedException(SessionLockedException::for($session)->getMessage());
        }

        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ACCEPTED_EXTENSIONS, true)) {
            throw new ActionRefusedException(__('conciliation.errors.file_type'));
        }

        $maxSizeMb = (int) config('conciliation.upload.max_size_mb');

        if ($file->getSize() > $maxSizeMb * 1024 * 1024) {
            throw new ActionRefusedException(__('conciliation.errors.file_size', ['max' => $maxSizeMb]));
        }

        $sha256 = hash_file('sha256', $file->getRealPath());

        $this->cancelPendingConfirmations($session, $slot);

        $activeFiles = $session->activeFiles()->get();

        if ($activeFiles->contains(fn ($active): bool => $active->slot === $slot && $active->sha256 === $sha256)) {
            return null;
        }

        $sibling = $slot->siblingPaymentSlot();

        if ($sibling !== null && $activeFiles->contains(fn ($active): bool => $active->slot === $sibling && $active->sha256 === $sha256)) {
            throw new ActionRefusedException(__('conciliation.errors.same_file_other_slot'));
        }

        $disk = (string) config('conciliation.upload.disk');
        $path = $file->storeAs('conciliation/incoming', Str::uuid().'.'.$extension, ['disk' => $disk]);

        $attempt = ImportAttempt::query()->create([
            'reconciliation_session_id' => $session->id,
            'slot' => $slot,
            'status' => ImportAttemptStatus::Queued,
            'user_id' => $user->id,
            'original_name' => $file->getClientOriginalName(),
            'disk' => $disk,
            'path' => $path,
            'size_bytes' => $file->getSize(),
            'sha256' => $sha256,
        ]);

        ProcessImportAttempt::dispatch($attempt->id);

        return $attempt;
    }

    /**
     * A new upload to the slot replaces any upload still waiting for confirmation.
     */
    protected function cancelPendingConfirmations(ReconciliationSession $session, ImportSlot $slot): void
    {
        $session->importAttempts()
            ->where('slot', $slot)
            ->where('status', ImportAttemptStatus::AwaitingConfirmation)
            ->get()
            ->each(fn (ImportAttempt $pending): bool => $this->cancelImportAttempt->handle($pending));
    }
}
