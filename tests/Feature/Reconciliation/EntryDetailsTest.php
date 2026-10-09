<?php

namespace Tests\Feature\Reconciliation;

use App\Livewire\Reconciliation\LinksTable;
use App\Livewire\Reconciliation\PendingTable;
use App\Models\PendingItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class EntryDetailsTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    public function test_authorization_and_payment_details_open_from_a_pending_row(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $this->authorization($session, 'MERCADO BOM PRECO CENTRO', 50000, [
            'request' => 'COFFEE BREAK DA REUNIAO - 00961',
            'payment_method' => 'Cartão de crédito',
            'card' => '0798',
            'payment_condition' => '2x',
            'row_number' => 14,
            'raw' => ['MAP_SOLICITANTE' => 'Ana Lima', 'MAP_CENTRO_DE_CUSTO' => 'Eventos', 'MAP_VAZIO' => null],
        ]);
        $payment = $this->payment($session, 'MERCADO BOM PRXYO', 50000, [
            'obligation_number' => '19634552',
            'operation_name' => 'MATERIAL DE CONSUMO',
            'species' => 'NOTA FISCAL FORNECED',
            'source_document' => '8929/1',
            'raw' => ['NM_CONTA_CORRENTE' => '1218220', 'DS_AUDIT' => 'Inserido por: ASSIST'],
        ]);
        $this->payment($session, 'MERCADO BOM PRXYO', 12345, ['obligation_number' => '19634552', 'operation_code' => '12006203', 'operation_name' => 'CUSTO DE AQUISICAO']);

        $this->reconcile($session);

        $item = PendingItem::query()->findOrFail('s-'.$payment->suggestions()->sole()->id);
        $screen = Livewire::actingAs($this->operator())->test(PendingTable::class, ['sessionId' => $session->id]);

        $screen->mountTableAction('authorizationDetails', $item)
            ->assertMountedActionModalSee([
                'COFFEE BREAK DA REUNIAO - 00961',
                'R$ 500,00',
                'Cartão de crédito',
                '0798',
                '2x',
                __('conciliation.reconciliation.details.spreadsheet_row', ['row' => 14]),
                'MAP_SOLICITANTE',
                'Ana Lima',
                'Eventos',
            ]);

        $screen->unmountAction()
            ->mountTableAction('paymentDetails', $item)
            ->assertMountedActionModalSee([
                'MERCADO BOM PRXYO',
                'MATERIAL DE CONSUMO',
                'NOTA FISCAL FORNECED',
                '19634552',
                '8929/1',
                __('conciliation.reconciliation.details.obligation_rows'),
                'CUSTO DE AQUISICAO',
                'R$ 123,45',
                'NM_CONTA_CORRENTE',
                'Inserido por: ASSIST',
            ]);
    }

    public function test_a_row_without_one_of_the_sides_offers_only_the_other(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $authorization = $this->authorization($session, 'SERRALHERIA UNIAO', 77700);
        $payment = $this->payment($session, 'POSTO ALFA', 9900);

        $this->reconcile($session);

        $withoutPayment = PendingItem::query()->findOrFail('a-'.$authorization->id);
        $withoutAuthorization = PendingItem::query()->findOrFail('p-'.$payment->id);

        Livewire::actingAs($this->operator())
            ->test(PendingTable::class, ['sessionId' => $session->id])
            ->assertTableActionVisible('authorizationDetails', $withoutPayment)
            ->assertTableActionHidden('paymentDetails', $withoutPayment)
            ->call('filterBy', 'unmatched_payment')
            ->assertTableActionHidden('authorizationDetails', $withoutAuthorization)
            ->assertTableActionVisible('paymentDetails', $withoutAuthorization);
    }

    public function test_details_open_from_a_reconciled_pair(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $this->authorization($session, 'PADARIA PERNAMBUCANA', 10000, ['request' => 'LANCHE DA VISITA - 00929']);
        $payment = $this->payment($session, 'PADARIA PERNAMBUCANA', 10000);

        $this->reconcile($session);

        Livewire::actingAs($this->operator())
            ->test(LinksTable::class, ['sessionId' => $session->id])
            ->mountTableAction('authorizationDetails', $payment->link)
            ->assertMountedActionModalSee(['LANCHE DA VISITA - 00929', __('conciliation.reconciliation.details.linked_payments')])
            ->unmountAction()
            ->mountTableAction('paymentDetails', $payment->link)
            ->assertMountedActionModalSee(__('conciliation.reconciliation.details.linked_to', ['supplier' => 'PADARIA PERNAMBUCANA', 'amount' => 'R$ 100,00']));
    }
}
