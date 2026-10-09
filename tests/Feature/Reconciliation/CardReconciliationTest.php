<?php

namespace Tests\Feature\Reconciliation;

use App\Enums\DifferenceTreatment;
use App\Enums\ImportAttemptStatus;
use App\Enums\ImportSlot;
use App\Livewire\Reconciliation\CardEntriesTable;
use App\Livewire\Reconciliation\CardsTable;
use App\Livewire\Reconciliation\LinksTable;
use App\Livewire\Reconciliation\PendingTable;
use App\Models\ExcludedOperationCode;
use App\Models\PaymentEntry;
use App\Models\PendingItem;
use App\Models\ReconciliationSession;
use App\Models\ReconciliationSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsReconciliations;
use Tests\Concerns\SubmitsSpreadsheets;
use Tests\TestCase;

class CardReconciliationTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;
    use SubmitsSpreadsheets;

    public function test_card_is_stored_when_payments_are_imported(): void
    {
        $session = ReconciliationSession::factory()->create();

        $attempt = $this->submit($session, ImportSlot::PaymentsSaude, $this->paymentsCsv([
            $this->paymentRow(['ESPECIE' => 'FATURA CARTAO 7607 (7613)']),
            $this->paymentRow(['ESPECIE' => 'FATURA CARTAO 4931 SOCIAL']),
            $this->paymentRow(['ESPECIE' => 'NOTA FISCAL FORNECED']),
        ], 'saude.csv'));

        $this->assertSame(ImportAttemptStatus::Accepted, $attempt->status);
        $this->assertSame(['7607', '4931', null], PaymentEntry::query()->orderBy('row_number')->pluck('card')->all());
    }

    public function test_different_cards_go_to_the_operator_with_a_warning(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $this->authorization($session, 'MERCADO LIVRE', 5120, ['payment_method' => 'Cartão de crédito', 'card' => '0798']);
        $this->payment($session, 'MERCADO LIVRE', 5120, ['card' => '4931', 'species' => 'FATURA CARTAO 4931 SOCIAL']);

        $this->reconcile($session);

        $this->assertTrue(ReconciliationSuggestion::query()->sole()->card_mismatch);

        Livewire::actingAs($this->operator())
            ->test(PendingTable::class, ['sessionId' => $session->id])
            ->assertSee(__('conciliation.reconciliation.warnings.card_mismatch'));
    }

    public function test_summary_and_statement_by_card(): void
    {
        ExcludedOperationCode::factory()->create(['code' => '11024465']);
        $session = $this->sessionWithFiles(state: 'open');
        $card = ['payment_method' => 'Cartão de crédito', 'card' => '0798'];
        $invoice = ['card' => '0798', 'species' => 'FATURA CARTAO 0798'];

        $this->authorization($session, 'MERCADO LIVRE', 5120, $card);
        $linked = $this->payment($session, 'MERCADO LIVRE', 5120, $invoice);
        $nextInvoice = $this->authorization($session, 'KALUNGA COMERCIO', 20000, $card);
        $withoutAuthorization = $this->payment($session, 'RESTAURANTE SABOR', 7000, $invoice);
        $this->payment($session, 'BANCO EMISSOR', 1350, [...$invoice, 'operation_code' => '11024465']);
        $onlyInvoice = $this->payment($session, 'POSTO ALFA', 1350, ['card' => '7222', 'species' => 'FATURA CARTAO 7222 (5561)']);
        $this->authorization($session, 'PADARIA PERNAMBUCANA', 9900);

        $this->reconcile($session);

        $screen = Livewire::actingAs($this->operator())->test(CardsTable::class, ['sessionId' => $session->id]);

        $this->assertSame([
            [
                'card' => '0798', 'authorizations' => 2, 'authorized_cents' => 25120, 'invoice_lines' => 2, 'invoice_cents' => 12120,
                'linked' => 1, 'authorizations_without_invoice' => 1, 'authorizations_without_invoice_cents' => 20000,
                'lines_without_authorization' => 1, 'lines_without_authorization_cents' => 7000, 'excluded' => 1,
            ],
            [
                'card' => '7222', 'authorizations' => 0, 'authorized_cents' => 0, 'invoice_lines' => 1, 'invoice_cents' => 1350,
                'linked' => 0, 'authorizations_without_invoice' => 0, 'authorizations_without_invoice_cents' => 0,
                'lines_without_authorization' => 1, 'lines_without_authorization_cents' => 1350, 'excluded' => 0,
            ],
        ], $screen->instance()->summary);

        $screen->assertDontSee(__('conciliation.reconciliation.cards.unpaid_heading', ['card' => '0798']))
            ->assertSeeHtml('x-on:click="$wire.choose(\'0798\')')
            ->call('choose', '0798')
            ->assertSet('card', '0798')
            ->assertSee(__('conciliation.reconciliation.cards.unpaid_heading', ['card' => '0798']))
            ->assertSeeLivewire(CardEntriesTable::class);

        Livewire::actingAs($this->operator())
            ->test(LinksTable::class, ['sessionId' => $session->id, 'card' => '0798'])
            ->assertCountTableRecords(1)
            ->assertCanSeeTableRecords([$linked->link])
            ->assertTableFilterHidden('card');

        Livewire::actingAs($this->operator())
            ->test(CardEntriesTable::class, ['sessionId' => $session->id, 'card' => '0798', 'kind' => 'authorizations'])
            ->assertCountTableRecords(1)
            ->assertCanSeeTableRecords([$nextInvoice])
            ->mountTableAction('authorizationDetails', $nextInvoice)
            ->assertMountedActionModalSee(['KALUNGA COMERCIO', 'R$ 200,00']);

        Livewire::actingAs($this->operator())
            ->test(CardEntriesTable::class, ['sessionId' => $session->id, 'card' => '0798', 'kind' => 'payments'])
            ->assertCountTableRecords(1)
            ->assertCanSeeTableRecords([$withoutAuthorization])
            ->mountTableAction('paymentDetails', $withoutAuthorization)
            ->assertMountedActionModalSee(['RESTAURANTE SABOR', 'R$ 70,00']);

        Livewire::actingAs($this->operator())
            ->test(CardEntriesTable::class, ['sessionId' => $session->id, 'card' => '7222', 'kind' => 'payments'])
            ->assertCanSeeTableRecords([$onlyInvoice]);

        Livewire::actingAs($this->operator())
            ->withQueryParams(['cartao' => '7222'])
            ->test(CardsTable::class, ['sessionId' => $session->id])
            ->assertSet('card', '7222')
            ->assertSee(__('conciliation.reconciliation.cards.orphans_heading', ['card' => '7222']));
    }

    public function test_card_entries_are_linked_by_hand_from_the_card_statement(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $order = $this->authorization($session, 'MERCADO LIVRE BRASIL EBAZAR', 30000, ['payment_method' => 'Cartão de crédito', 'card' => '0798']);
        $seller = $this->payment($session, 'PAPELARIA DOIS IRMAOS', 30000, ['card' => '0798', 'species' => 'FATURA CARTAO 0798']);
        $ticket = $this->authorization($session, 'AGENCIA DE VIAGENS', 45000, ['payment_method' => 'Cartão de crédito', 'card' => '0798']);
        $airline = $this->payment($session, 'COMPANHIA AEREA', 40000, ['card' => '0798', 'species' => 'FATURA CARTAO 0798']);
        $this->reconcile($session);

        Livewire::actingAs($this->operator())
            ->test(CardEntriesTable::class, ['sessionId' => $session->id, 'card' => '0798', 'kind' => 'authorizations'])
            ->assertCountTableRecords(2)
            ->mountTableAction('link', $order)
            ->assertMountedActionModalSee(['PAPELARIA DOIS IRMAOS', 'COMPANHIA AEREA'])
            ->setTableActionData(['payments' => [$seller->id]])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors()
            ->assertDispatched('reconciliation-changed')
            ->assertCountTableRecords(1);

        $this->assertSame($order->id, $seller->refresh()->link->authorization_entry_id);

        $screen = Livewire::actingAs($this->operator())
            ->test(CardEntriesTable::class, ['sessionId' => $session->id, 'card' => '0798', 'kind' => 'payments']);

        $screen->assertCountTableRecords(1)
            ->mountTableAction('linkToAuthorization', $airline)
            ->assertMountedActionModalSee(['AGENCIA DE VIAGENS', 'R$ 450,00'])
            ->setTableActionData(['authorization' => $ticket->id])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['treatment' => 'required']);

        $screen->setTableActionData(['authorization' => $ticket->id, 'treatment' => DifferenceTreatment::StillOwed->value])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors()
            ->assertDispatched('banner-message', style: 'success', message: __('conciliation.reconciliation.actions.linked'))
            ->assertCountTableRecords(0);

        $this->assertSame($ticket->id, $airline->refresh()->link->authorization_entry_id);
        $this->assertSame(5000, $ticket->refresh()->balanceCents());
    }

    public function test_a_payment_without_authorization_is_linked_from_the_pending_list(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $authorization = $this->authorization($session, 'AGENCIA DE VIAGENS', 45000);
        $payment = $this->payment($session, 'COMPANHIA AEREA', 45000);
        $this->reconcile($session);
        $item = PendingItem::query()->findOrFail('p-'.$payment->id);

        Livewire::actingAs($this->operator())
            ->test(PendingTable::class, ['sessionId' => $session->id])
            ->assertTableActionHidden('linkToAuthorization', PendingItem::query()->findOrFail('a-'.$authorization->id))
            ->call('filterBy', 'unmatched_payment')
            ->assertTableActionVisible('linkToAuthorization', $item)
            ->mountTableAction('linkToAuthorization', $item)
            ->setTableActionData(['authorization' => $authorization->id])
            ->callMountedTableAction()
            ->assertDispatched('banner-message', style: 'success', message: __('conciliation.reconciliation.actions.linked'));

        $this->assertSame($authorization->id, $payment->refresh()->link->authorization_entry_id);
    }

    public function test_pending_and_reconciled_lists_can_be_filtered_by_card(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $this->authorization($session, 'MERCADO LIVRE', 5120, ['payment_method' => 'Cartão de crédito', 'card' => '0798']);
        $linked = $this->payment($session, 'MERCADO LIVRE', 5120, ['card' => '0798']);
        $onCard = $this->authorization($session, 'KALUNGA COMERCIO', 20000, ['payment_method' => 'Cartão de crédito', 'card' => '0798']);
        $otherCard = $this->authorization($session, 'LIVRARIA CULTURA', 30000, ['payment_method' => 'Cartão de crédito', 'card' => '4931']);
        $this->authorization($session, 'PADARIA PERNAMBUCANA', 9900);
        $this->payment($session, 'PADARIA PERNAMBUCANA', 9900);

        $this->reconcile($session);

        Livewire::actingAs($this->operator())
            ->test(PendingTable::class, ['sessionId' => $session->id])
            ->assertCountTableRecords(2)
            ->filterTable('card', '0798')
            ->assertCanSeeTableRecords([PendingItem::query()->findOrFail('a-'.$onCard->id)])
            ->assertCanNotSeeTableRecords([PendingItem::query()->findOrFail('a-'.$otherCard->id)])
            ->assertCountTableRecords(1);

        Livewire::actingAs($this->operator())
            ->test(LinksTable::class, ['sessionId' => $session->id])
            ->assertCountTableRecords(2)
            ->filterTable('card', '0798')
            ->assertCanSeeTableRecords([$linked->link])
            ->assertCountTableRecords(1);
    }
}
