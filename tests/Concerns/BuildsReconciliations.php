<?php

namespace Tests\Concerns;

use App\Contracts\ReconciliationEngine;
use App\Enums\ImportSlot;
use App\Enums\LinkOrigin;
use App\Enums\SessionStatus;
use App\Models\AuthorizationEntry;
use App\Models\ImportFile;
use App\Models\PaymentEntry;
use App\Models\ReconciliationLink;
use App\Models\ReconciliationRun;
use App\Models\ReconciliationSession;
use App\Models\ReconciliationSettings;
use App\Models\User;
use App\Services\Reconciliation\EngineParametersFactory;
use App\Services\Reconciliation\LinkAttributes;
use App\Services\Reconciliation\ReconciliationLinker;
use Illuminate\Support\Facades\DB;

trait BuildsReconciliations
{
    /**
     * Tests are written against the fixed tolerance of R$ 0,50; the ones about the
     * percentage tolerance set it themselves.
     */
    protected function setUpBuildsReconciliations(): void
    {
        $this->useTolerance(50);
    }

    protected function useTolerance(int $cents, ?int $basisPoints = null, ?int $capCents = null): void
    {
        ReconciliationSettings::current()->update([
            'tolerance_cents' => $cents,
            'tolerance_basis_points' => $basisPoints,
            'tolerance_cap_cents' => $capCents,
        ]);
    }

    /**
     * A session with one active file per slot, ready to receive entries.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function sessionWithFiles(string $period = '2026-07-01', array $attributes = [], string $state = 'processed'): ReconciliationSession
    {
        $factory = ReconciliationSession::factory();
        $factory = $state === 'open' ? $factory : $factory->{$state}();

        $session = $factory->create(['period' => $period, ...$attributes]);

        foreach (ImportSlot::cases() as $slot) {
            ImportFile::factory()->forSlot($slot)->create(['reconciliation_session_id' => $session->id]);
        }

        return $session->load('activeFiles');
    }

    protected function fileOf(ReconciliationSession $session, ImportSlot $slot): ImportFile
    {
        return $session->activeFiles()->where('slot', $slot)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function authorization(ReconciliationSession $session, string $supplier, int $amountCents, array $attributes = []): AuthorizationEntry
    {
        return AuthorizationEntry::factory()->create([
            'import_file_id' => $this->fileOf($session, ImportSlot::Authorizations)->id,
            'reconciliation_session_id' => $session->id,
            'supplier_name' => $supplier,
            'amount_cents' => $amountCents,
            'authorized_on' => $session->period->format('Y-m-').'05',
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function payment(ReconciliationSession $session, string $supplier, int $amountCents, array $attributes = [], ImportSlot $slot = ImportSlot::PaymentsSaude): PaymentEntry
    {
        static $sequence = 0;
        $sequence++;

        $code = $attributes['operation_code'] ?? '11022276';

        return PaymentEntry::factory()->create([
            'import_file_id' => $this->fileOf($session, $slot)->id,
            'reconciliation_session_id' => $session->id,
            'unit' => $slot->unit()->value,
            'supplier_name' => $supplier,
            'amount_cents' => $amountCents,
            'obligation_amount_cents' => $amountCents,
            'paid_on' => $session->period->format('Y-m-').'20',
            'operation_code' => $code,
            'obligation_number' => 'OB'.$sequence,
            'account_movement' => 'MV'.$sequence,
            'identity_key' => 'OB'.$sequence.'|'.$code.'|MV'.$sequence,
            ...$attributes,
        ]);
    }

    protected function runFor(ReconciliationSession $session, array $attributes = []): ReconciliationRun
    {
        return ReconciliationRun::factory()->create(['reconciliation_session_id' => $session->id, ...$attributes]);
    }

    /**
     * Link through the single path every link goes through.
     *
     * @param  array<string, mixed>  $attributes  Named arguments of LinkAttributes
     */
    protected function linkPair(AuthorizationEntry $authorization, PaymentEntry $payment, ?ReconciliationRun $run = null, array $attributes = []): ReconciliationLink
    {
        $run ??= ReconciliationRun::query()
            ->where('reconciliation_session_id', $payment->reconciliation_session_id)
            ->latest('id')
            ->first() ?? $this->runFor($payment->session);

        return DB::transaction(fn (): ReconciliationLink => app(ReconciliationLinker::class)->link(
            $authorization,
            $payment,
            new LinkAttributes(...[
                'run' => $run,
                'parameters' => app(EngineParametersFactory::class)->fromRun($run),
                'origin' => LinkOrigin::Automatic,
                ...$attributes,
            ]),
        ));
    }

    protected function unlinkPair(ReconciliationLink $link): void
    {
        DB::transaction(fn () => app(ReconciliationLinker::class)->unlink(
            $link,
            app(EngineParametersFactory::class)->fromRun($link->run),
        ));
    }

    /**
     * Run the engine as the queued job does: the session is locked, reconciled and marked as processed.
     */
    protected function reconcile(ReconciliationSession $session): ReconciliationRun
    {
        $session->update(['status' => SessionStatus::Processing, 'processing_started_at' => now()]);

        $engine = app(ReconciliationEngine::class);
        $engine->discardResult($session);
        $engine->run($session, fn (int $percent) => null);

        $session->update([
            'status' => SessionStatus::Processed,
            'first_processed_at' => $session->first_processed_at ?? now(),
            'processed_at' => now(),
        ]);

        return $session->refresh()->currentRun;
    }

    protected function operator(): User
    {
        return User::factory()->create();
    }
}
