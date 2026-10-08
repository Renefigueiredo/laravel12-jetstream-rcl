<?php

namespace Tests\Concerns;

use App\Actions\Conciliation\SubmitSpreadsheet;
use App\Enums\ImportSlot;
use App\Models\ImportAttempt;
use App\Models\ReconciliationSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait SubmitsSpreadsheets
{
    use BuildsSpreadsheets;

    protected function setUpSubmitsSpreadsheets(): void
    {
        Storage::fake('local');
    }

    /**
     * Submit a spreadsheet as the given user; the queue runs synchronously in tests.
     */
    protected function submit(ReconciliationSession $session, ImportSlot $slot, UploadedFile $file, ?User $user = null): ?ImportAttempt
    {
        return app(SubmitSpreadsheet::class)
            ->handle($user ?? $session->creator, $session, $slot, $file)
            ?->fresh();
    }

    /**
     * Load a valid file into each of the three slots of the session.
     */
    protected function loadAllSlots(ReconciliationSession $session): void
    {
        $this->submit($session, ImportSlot::Authorizations, $this->authorizationsXlsx($this->authorizationRows(2)));
        $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(2), 'social.csv'));
        $this->submit($session, ImportSlot::PaymentsSaude, $this->paymentsCsv($this->paymentRows(2), 'saude.csv'));
    }
}
