<?php

namespace Tests\Feature\Reconciliation;

use App\Livewire\Reconciliation\CardEntriesTable;
use App\Livewire\Reconciliation\EarlyPaymentsTable;
use App\Livewire\Reconciliation\ExcludedTable;
use App\Livewire\Reconciliation\InvestigationTable;
use App\Livewire\Reconciliation\LinksTable;
use App\Livewire\Reconciliation\PendingTable;
use App\Models\ExcludedOperationCode;
use App\Models\PendingItem;
use App\Models\ReconciliationSkip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class SessionListsSearchTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    public function test_reconciled_pairs_are_found_and_filtered_by_either_side(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $this->authorization($session, 'GRAFICA SUL', 28870, ['request' => 'TONER - 00123', 'authorized_on' => '2026-07-02']);
        $ink = $this->payment($session, 'GRAFICA SUL', 28870, ['paid_on' => '2026-07-10', 'operation_name' => 'MATERIAL GRAFICO']);
        $this->authorization($session, 'PADARIA PERNAMBUCANA', 500000, ['request' => 'COFFEE BREAK - 00456', 'authorized_on' => '2026-07-20']);
        $bread = $this->payment($session, 'PADARIA PERNAMBUCANA', 500000, ['paid_on' => '2026-07-25', 'operation_code' => '11039999']);
        $this->reconcile($session);

        $screen = Livewire::actingAs($this->operator())->test(LinksTable::class, ['sessionId' => $session->id]);

        foreach ([
            'PADARIA' => $bread,
            'toner' => $ink,
            'grafico' => $ink,
            '11039999' => $bread,
            '288,70' => $ink,
            '5.000,00' => $bread,
        ] as $search => $payment) {
            $screen->searchTable((string) $search)->assertCountTableRecords(1)->assertCanSeeTableRecords([$payment->link]);
        }

        $screen->searchTable('')
            ->filterTable('authorized', ['until' => '2026-07-10'])
            ->assertCanSeeTableRecords([$ink->link])
            ->assertCanNotSeeTableRecords([$bread->link])
            ->resetTableFilters()
            ->filterTable('paid', ['from' => '2026-07-20'])
            ->assertCanSeeTableRecords([$bread->link])
            ->assertCanNotSeeTableRecords([$ink->link])
            ->resetTableFilters()
            ->filterTable('authorized_amount', ['from' => '1.000,00'])
            ->assertCanSeeTableRecords([$bread->link])
            ->assertCanNotSeeTableRecords([$ink->link])
            ->resetTableFilters()
            ->filterTable('paid_amount', ['until' => '300,00'])
            ->assertCanSeeTableRecords([$ink->link])
            ->assertCanNotSeeTableRecords([$bread->link]);
    }

    public function test_pending_items_are_found_and_filtered_by_either_side(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $partial = $this->authorization($session, 'GRAFICA SUL', 90000, ['request' => 'CARTAZES - 00321', 'authorized_on' => '2026-07-02']);
        $partOfIt = $this->payment($session, 'GRAFICA SUL', 28870, ['paid_on' => '2026-07-10']);
        $unpaid = $this->authorization($session, 'LIVRARIA CULTURA', 40000, ['request' => 'LIVROS - 00654', 'authorized_on' => '2026-07-25']);
        $this->reconcile($session);
        $suggestion = PendingItem::query()->findOrFail('s-'.$partOfIt->suggestions()->sole()->id);
        $alone = PendingItem::query()->findOrFail('a-'.$unpaid->id);

        $screen = Livewire::actingAs($this->operator())->test(PendingTable::class, ['sessionId' => $session->id]);

        $screen->assertCountTableRecords(2)
            ->searchTable('cartazes')->assertCountTableRecords(1)->assertCanSeeTableRecords([$suggestion])
            ->searchTable('288,70')->assertCountTableRecords(1)->assertCanSeeTableRecords([$suggestion])
            ->searchTable('400,00')->assertCountTableRecords(1)->assertCanSeeTableRecords([$alone])
            ->searchTable('LIVRARIA')->assertCountTableRecords(1)->assertCanSeeTableRecords([$alone])
            ->searchTable('')
            ->filterTable('authorized', ['from' => '2026-07-20'])
            ->assertCanSeeTableRecords([$alone])
            ->assertCanNotSeeTableRecords([$suggestion])
            ->resetTableFilters()
            ->filterTable('authorized_amount', ['from' => '500,00'])
            ->assertCanSeeTableRecords([$suggestion])
            ->assertCanNotSeeTableRecords([$alone])
            ->resetTableFilters()
            ->filterTable('paid_amount', ['until' => '300,00'])
            ->assertCanSeeTableRecords([$suggestion])
            ->assertCanNotSeeTableRecords([$alone]);

        $this->assertSame(90000, $partial->balanceCents());
    }

    public function test_payment_lists_are_found_and_filtered(): void
    {
        ExcludedOperationCode::factory()->create(['code' => '11024465']);
        $session = $this->sessionWithFiles(state: 'open');
        $card = ['card' => '0798', 'species' => 'FATURA CARTAO 0798'];
        $fuel = $this->payment($session, 'POSTO ALFA', 7000, [...$card, 'paid_on' => '2026-07-03', 'operation_name' => 'COMBUSTIVEL']);
        $meal = $this->payment($session, 'RESTAURANTE SABOR', 28870, [...$card, 'paid_on' => '2026-07-18', 'operation_name' => 'ALIMENTACAO']);
        $fee = $this->payment($session, 'BANCO EMISSOR', 1350, ['operation_code' => '11024465', 'paid_on' => '2026-07-05']);
        $tax = $this->payment($session, 'RECEITA FEDERAL', 990000, ['operation_code' => '11024465', 'paid_on' => '2026-07-28']);
        $this->reconcile($session);
        $operator = $this->operator();

        foreach ([
            Livewire::actingAs($operator)->test(InvestigationTable::class, ['sessionId' => $session->id]),
            Livewire::actingAs($operator)->test(CardEntriesTable::class, ['sessionId' => $session->id, 'card' => '0798', 'kind' => 'payments']),
        ] as $screen) {
            $screen->assertCountTableRecords(2)
                ->searchTable('combust')->assertCountTableRecords(1)->assertCanSeeTableRecords([$fuel])
                ->searchTable('288,70')->assertCountTableRecords(1)->assertCanSeeTableRecords([$meal])
                ->searchTable('RESTAURANTE')->assertCountTableRecords(1)->assertCanSeeTableRecords([$meal])
                ->searchTable('')
                ->filterTable('paid', ['from' => '2026-07-10'])
                ->assertCanSeeTableRecords([$meal])
                ->assertCanNotSeeTableRecords([$fuel])
                ->resetTableFilters()
                ->filterTable('paid_amount', ['until' => '100,00'])
                ->assertCanSeeTableRecords([$fuel])
                ->assertCanNotSeeTableRecords([$meal]);
        }

        $feeSkip = ReconciliationSkip::query()->where('payment_entry_id', $fee->id)->sole();
        $taxSkip = ReconciliationSkip::query()->where('payment_entry_id', $tax->id)->sole();

        Livewire::actingAs($operator)
            ->test(ExcludedTable::class, ['sessionId' => $session->id])
            ->assertCountTableRecords(2)
            ->searchTable('RECEITA')->assertCountTableRecords(1)->assertCanSeeTableRecords([$taxSkip])
            ->searchTable('13,50')->assertCountTableRecords(1)->assertCanSeeTableRecords([$feeSkip])
            ->searchTable('')
            ->filterTable('paid', ['until' => '2026-07-10'])
            ->assertCanSeeTableRecords([$feeSkip])
            ->assertCanNotSeeTableRecords([$taxSkip])
            ->resetTableFilters()
            ->filterTable('paid_amount', ['from' => '1.000,00'])
            ->assertCanSeeTableRecords([$taxSkip])
            ->assertCanNotSeeTableRecords([$feeSkip]);
    }

    public function test_card_authorizations_and_early_payments_are_found_and_filtered(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $card = ['payment_method' => 'Cartão de crédito', 'card' => '0798'];
        $books = $this->authorization($session, 'LIVRARIA CULTURA', 40000, [...$card, 'request' => 'LIVROS - 00654', 'authorized_on' => '2026-07-02']);
        $tickets = $this->authorization($session, 'AGENCIA DE VIAGENS', 250000, [...$card, 'request' => 'PASSAGENS - 00987', 'authorized_on' => '2026-07-25']);
        $this->authorization($session, 'MERCADO BOM PRECO CENTRO', 50000, ['authorized_on' => '2026-07-20', 'request' => 'MANTIMENTOS - 00111']);
        $early = $this->payment($session, 'MERCADO BOM PRXYO', 50000, ['paid_on' => '2026-07-10']);
        $this->reconcile($session);
        $operator = $this->operator();

        Livewire::actingAs($operator)
            ->test(CardEntriesTable::class, ['sessionId' => $session->id, 'card' => '0798', 'kind' => 'authorizations'])
            ->assertCountTableRecords(2)
            ->searchTable('passagens')->assertCountTableRecords(1)->assertCanSeeTableRecords([$tickets])
            ->searchTable('400,00')->assertCountTableRecords(1)->assertCanSeeTableRecords([$books])
            ->searchTable('')
            ->filterTable('authorized', ['from' => '2026-07-20'])
            ->assertCanSeeTableRecords([$tickets])
            ->assertCanNotSeeTableRecords([$books])
            ->resetTableFilters()
            ->filterTable('authorized_amount', ['until' => '1.000,00'])
            ->assertCanSeeTableRecords([$books])
            ->assertCanNotSeeTableRecords([$tickets]);

        $suggestion = $early->suggestions()->sole();

        $this->assertTrue($suggestion->paid_before_authorization);

        Livewire::actingAs($operator)
            ->test(EarlyPaymentsTable::class, ['sessionId' => $session->id])
            ->assertCountTableRecords(1)
            ->searchTable('mantimentos')->assertCountTableRecords(1)
            ->searchTable('500,00')->assertCountTableRecords(1)
            ->searchTable('nada disso')->assertCountTableRecords(0)
            ->searchTable('')
            ->filterTable('paid', ['from' => '2026-07-15'])
            ->assertCountTableRecords(0)
            ->resetTableFilters()
            ->filterTable('authorized_amount', ['from' => '100,00'])
            ->assertCanSeeTableRecords([$suggestion]);
    }
}
