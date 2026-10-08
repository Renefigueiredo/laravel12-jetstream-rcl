<?php

namespace App\Actions\Conciliation;

use App\Enums\ImportAttemptStatus;
use App\Models\ImportAttempt;

class CancelImportAttempt
{
    /**
     * Discard an upload that is waiting for the period divergence confirmation.
     */
    public function handle(ImportAttempt $attempt): bool
    {
        $cancelled = ImportAttempt::query()
            ->whereKey($attempt->id)
            ->where('status', ImportAttemptStatus::AwaitingConfirmation)
            ->update(['status' => ImportAttemptStatus::Cancelled]);

        if ($cancelled === 0) {
            return false;
        }

        $attempt->deleteReceivedFile();
        $attempt->refresh();

        return true;
    }
}
