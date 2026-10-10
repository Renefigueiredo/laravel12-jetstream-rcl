<?php

namespace Tests\Feature\Reconciliation;

use App\Actions\Conciliation\ActionRefusedException;
use App\Enums\DifferenceTreatment;
use App\Enums\ImportFileStatus;
use App\Enums\ImportSlot;
use App\Models\PaymentEntry;
use App\Services\Reconciliation\AuthorizationAvailability;
use App\Services\Reconciliation\Matching\EngineParameters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class AuthorizationAvailabilityTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    public function test_a_payment_may_go_to_open_authorizations_of_its_session_and_of_earlier_ones(): void
    {
        $january = $this->processedSession('2026-01-01');
        $may = $this->processedSession('2026-05-01');
        $july = $this->processedSession('2026-07-01');
        $august = $this->processedSession('2026-08-01');
        $september = $this->processedSession('2026-09-01');
        $notProcessed = $this->sessionWithFiles('2026-06-01', state: 'open');

        $sameSession = $this->authorization($august, 'DA SESSAO', 10000);
        $inWindow = $this->authorization($july, 'NA JANELA', 10000);
        $atTheEdge = $this->authorization($may, 'NO LIMITE DA JANELA', 10000);
        $oldUntouched = $this->authorization($january, 'ANTIGA SEM PAGAMENTO', 10000);
        $oldReceiving = $this->authorization($january, 'ANTIGA COM PAGAMENTO', 90000);
        $this->linkedPayment($january, $oldReceiving, 30000, link: ['treatment' => DifferenceTreatment::StillOwed]);
        $settled = $this->authorization($july, 'QUITADA', 10000);
        $this->linkedPayment($july, $settled, 10000);
        $later = $this->authorization($september, 'DE PERIODO POSTERIOR', 10000);
        $ofOpenSession = $this->authorization($notProcessed, 'DE SESSAO ABERTA', 10000);
        $replaced = $this->authorization($july, 'DE PLANILHA SUBSTITUIDA', 10000);
        $replacedFile = $this->fileOf($july, ImportSlot::Authorizations)->replicate();
        $replacedFile->status = ImportFileStatus::Replaced;
        $replacedFile->save();
        $replaced->update(['import_file_id' => $replacedFile->id]);

        $payment = PaymentEntry::query()->with('session')->findOrFail($this->payment($august, 'QUALQUER', 10000)->id);
        $availability = app(AuthorizationAvailability::class);
        $parameters = new EngineParameters(lookbackMonths: 3);

        $this->assertEqualsCanonicalizing(
            [$sameSession->id, $inWindow->id, $atTheEdge->id, $oldReceiving->id],
            $availability->forPayment($payment, $parameters)->pluck('id')->all(),
        );

        foreach ([$sameSession, $inWindow, $atTheEdge, $oldReceiving] as $authorization) {
            $availability->assertCanReceive($authorization->load(['state', 'session', 'importFile']), $payment, $parameters);
        }

        foreach ([
            [$oldUntouched, 'authorization_not_available'],
            [$settled, 'authorization_settled'],
            [$later, 'authorization_not_available'],
            [$ofOpenSession, 'authorization_not_available'],
            [$replaced, 'authorization_not_available'],
        ] as [$authorization, $error]) {
            try {
                $availability->assertCanReceive($authorization->load(['state', 'session', 'importFile']), $payment, $parameters);
                $this->fail('Accepted: '.$authorization->supplier_name);
            } catch (ActionRefusedException $exception) {
                $this->assertSame(__('conciliation.reconciliation.errors.'.$error), $exception->getMessage());
            }
        }
    }
}
