<?php

namespace Tests\Feature\Reconciliation;

use App\Enums\AuthorizationStatus;
use App\Enums\DifferenceTreatment;
use App\Enums\SuggestionStatus;
use App\Livewire\Reconciliation\LinksTable;
use App\Livewire\Reconciliation\PendingTable;
use App\Livewire\Reconciliation\Show;
use App\Models\PendingItem;
use App\Models\ReconciliationLink;
use App\Models\ReconciliationSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class DecisionScreenTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    protected function item(string $id): PendingItem
    {
        return PendingItem::query()->findOrFail($id);
    }

    public function test_a_doubtful_pair_is_confirmed_or_rejected_from_the_list(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $this->authorization($session, 'MERCADO BOM PRECO CENTRO', 50000);
        $confirmed = $this->payment($session, 'MERCADO BOM PRXYO', 50000);
        $this->authorization($session, 'GRAFICA RAPIDA CENTRO', 41105);
        $rejected = $this->payment($session, 'GRAFICA RAPIDAS SUL', 41105);
        $this->reconcile($session);

        $toConfirm = $this->item('s-'.$confirmed->suggestions()->sole()->id);
        $toReject = $this->item('s-'.$rejected->suggestions()->sole()->id);

        Livewire::actingAs($this->operator())
            ->test(PendingTable::class, ['sessionId' => $session->id])
            ->assertTableActionVisible('confirm', $toConfirm)
            ->assertTableActionVisible('reject', $toConfirm)
            ->assertTableActionHidden('link', $toConfirm)
            ->callTableAction('confirm', $toConfirm)
            ->assertHasNoTableActionErrors()
            ->assertDispatched('reconciliation-changed')
            ->assertDispatched('banner-message', style: 'success', message: __('conciliation.reconciliation.actions.confirmed'))
            ->assertCanNotSeeTableRecords([$toConfirm])
            ->callTableAction('reject', $toReject)
            ->assertDispatched('banner-message', style: 'success', message: __('conciliation.reconciliation.actions.rejected'));

        $this->assertNotNull($confirmed->link);
        $this->assertSame(SuggestionStatus::Rejected, $rejected->suggestions()->sole()->status);
    }

    public function test_a_partial_pair_asks_what_the_difference_means(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $authorization = $this->authorization($session, 'CONSTRUTORA HORIZONTE', 100000);
        $payment = $this->payment($session, 'CONSTRUTORA HORIZONTE', 95000);
        $this->reconcile($session);
        $item = $this->item('s-'.$payment->suggestions()->sole()->id);

        $screen = Livewire::actingAs($this->operator())->test(PendingTable::class, ['sessionId' => $session->id]);

        $screen->callTableAction('confirm', $item, data: [])
            ->assertHasTableActionErrors(['treatment' => 'required']);

        $screen->setTableActionData(['treatment' => 'discount'])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['category' => 'required', 'justification' => 'required']);

        $this->assertSame(0, ReconciliationLink::query()->count());

        $screen->setTableActionData([
            'treatment' => 'discount',
            'category' => 'commercial_discount',
            'justification' => 'Desconto negociado na compra',
        ])->callMountedTableAction()->assertHasNoTableActionErrors();

        $this->assertSame(DifferenceTreatment::Discount, $payment->link->treatment);
        $this->assertSame(5000, $payment->link->discount_cents);
        $this->assertSame(AuthorizationStatus::Reconciled, $authorization->refresh()->status());
    }

    public function test_an_excess_above_the_cap_is_refused_with_the_reason(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $this->authorization($session, 'GRAFICA RAPIDA', 50000);
        $payment = $this->payment($session, 'GRAFICA RAPIDA', 65000);
        $this->reconcile($session);
        $item = $this->item('s-'.$payment->suggestions()->sole()->id);

        Livewire::actingAs($this->operator())
            ->test(PendingTable::class, ['sessionId' => $session->id])
            ->callTableAction('confirm', $item, data: ['treatment' => 'accepted_surcharge', 'category' => 'freight', 'justification' => 'Frete'])
            ->assertSet('showingRefusal', true)
            ->assertSee(__('conciliation.reconciliation.errors.surcharge_above_cap', ['cap' => '10']))
            ->callTableAction('confirm', $item, data: ['treatment' => 'overpayment'])
            ->assertDispatched('banner-message', style: 'success', message: __('conciliation.reconciliation.actions.confirmed'));

        $this->assertSame(DifferenceTreatment::Overpayment, $payment->link->treatment);

        Livewire::actingAs($this->operator())
            ->test(LinksTable::class, ['sessionId' => $session->id])
            ->assertTableColumnFormattedStateSet('treatment', DifferenceTreatment::Overpayment->label(), $payment->link)
            ->filterTable('treatment', 'overpayment')
            ->assertCountTableRecords(1);
    }

    public function test_several_payments_are_linked_to_one_authorization_from_the_list(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $order = $this->authorization($session, 'MERCADO LIVRE BRASIL EBAZAR', 66313, ['payment_method' => 'Cartão de crédito', 'card' => '0798']);
        $first = $this->payment($session, 'PAPELARIA DOIS IRMAOS', 20000, ['card' => '0798']);
        $second = $this->payment($session, 'DISTRIBUIDORA ALFA', 46313, ['card' => '0798']);
        $otherCard = $this->payment($session, 'POSTO ALFA', 66313, ['card' => '4931']);
        $this->reconcile($session);
        $item = $this->item('a-'.$order->id);

        $screen = Livewire::actingAs($this->operator())->test(PendingTable::class, ['sessionId' => $session->id]);

        $screen->assertTableActionVisible('link', $item)
            ->assertTableActionHidden('confirm', $item)
            ->mountTableAction('link', $item)
            ->assertMountedActionModalSee(['PAPELARIA DOIS IRMAOS', 'DISTRIBUIDORA ALFA', 'POSTO ALFA', 'R$ 663,13'])
            ->assertMountedActionModalSee([
                __('conciliation.reconciliation.picker.total'),
                __('conciliation.reconciliation.picker.balance'),
                __('conciliation.reconciliation.picker.difference'),
            ])
            ->setTableActionData(['payments' => [$first->id, $second->id]])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors()
            ->assertDispatched('banner-message', style: 'success', message: __('conciliation.reconciliation.actions.linked'));

        $this->assertSame(AuthorizationStatus::Reconciled, $order->refresh()->status());
        $this->assertSame(2, $order->state->links_count);
        $this->assertNull($otherCard->link);
    }

    public function test_an_open_balance_is_closed_with_a_discount_from_the_list(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $authorization = $this->authorization($session, 'CONSTRUTORA HORIZONTE', 100000);
        $payment = $this->payment($session, 'CONSTRUTORA HORIZONTE', 96000);
        $this->reconcile($session);

        $screen = Livewire::actingAs($this->operator())->test(PendingTable::class, ['sessionId' => $session->id]);

        $screen->callTableAction('confirm', $this->item('s-'.$payment->suggestions()->sole()->id), data: ['treatment' => 'still_owed']);

        $open = $this->item('a-'.$authorization->id);

        $this->assertSame('open_balance', $open->classification);

        $screen->assertTableColumnStateSet('payment_supplier', trans_choice('conciliation.reconciliation.columns.linked_payments', 1, ['count' => 1]), $open)
            ->assertTableColumnFormattedStateSet('payment.amount_cents', 'R$ 960,00', $open)
            ->assertTableColumnFormattedStateSet('difference_cents', '-R$ 40,00', $open)
            ->assertSee('CONSTRUTORA HORIZONTE · R$ 960,00');

        $screen->assertTableActionVisible('closeWithDiscount', $open)
            ->assertTableActionVisible('link', $open)
            ->callTableAction('closeWithDiscount', $open, data: [])
            ->assertHasTableActionErrors(['category' => 'required', 'justification' => 'required'])
            ->setTableActionData(['category' => 'other', 'justification' => 'Saldo não será cobrado'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(AuthorizationStatus::Reconciled, $authorization->refresh()->status());
        $this->assertSame(4000, $payment->link->discount_cents);
    }

    public function test_selected_doubtful_pairs_are_confirmed_together(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $this->authorization($session, 'MERCADO BOM PRECO CENTRO', 50000);
        $first = $this->payment($session, 'MERCADO BOM PRXYO', 50000);
        $this->authorization($session, 'GRAFICA RAPIDA CENTRO', 41105);
        $second = $this->payment($session, 'GRAFICA RAPIDAS SUL', 41105);
        $this->reconcile($session);
        $items = [$this->item('s-'.$first->suggestions()->sole()->id), $this->item('s-'.$second->suggestions()->sole()->id)];

        Livewire::actingAs($this->operator())
            ->test(PendingTable::class, ['sessionId' => $session->id])
            ->callTableBulkAction('confirmSelected', $items)
            ->assertDispatched('banner-message', style: 'success', message: __('conciliation.reconciliation.actions.confirmed_selected', ['confirmed' => 2, 'refused' => 0]));

        $this->assertSame(2, ReconciliationLink::query()->whereNotNull('decided_by')->count());
        $this->assertSame(2, ReconciliationSuggestion::query()->where('status', SuggestionStatus::Confirmed)->count());
    }

    public function test_a_pair_is_unlinked_from_the_reconciled_list_and_the_summary_follows(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $authorization = $this->authorization($session, 'PADARIA PERNAMBUCANA', 10000);
        $payment = $this->payment($session, 'PADARIA PERNAMBUCANA', 10000);
        $this->reconcile($session);
        $operator = $this->operator();

        $page = Livewire::actingAs($operator)->test(Show::class, ['session' => $session]);

        $this->assertSame(1, $page->instance()->totals['reconciled_automatically']);

        Livewire::actingAs($operator)
            ->test(LinksTable::class, ['sessionId' => $session->id])
            ->assertTableActionVisible('unlink', $payment->link)
            ->callTableAction('unlink', $payment->link)
            ->assertDispatched('reconciliation-changed')
            ->assertDispatched('banner-message', style: 'success', message: __('conciliation.reconciliation.actions.unlinked'))
            ->assertCountTableRecords(0);

        $page->dispatch('reconciliation-changed');

        $this->assertSame(0, $page->instance()->totals['reconciled_automatically']);
        $this->assertSame(1, $page->instance()->totals['unmatched_authorizations']);
        $this->assertSame(AuthorizationStatus::Open, $authorization->refresh()->status());
    }

    public function test_a_link_paid_in_a_later_session_cannot_be_undone_from_the_earlier_one(): void
    {
        $july = $this->sessionWithFiles('2026-07-01', state: 'open');
        $this->authorization($july, 'MERCADO LIVRE', 32000);
        $this->reconcile($july);
        $august = $this->sessionWithFiles('2026-08-01', state: 'open');
        $payment = $this->payment($august, 'MERCADO LIVRE', 32000);
        $this->reconcile($august);

        Livewire::actingAs($this->operator())
            ->test(LinksTable::class, ['sessionId' => $july->id])
            ->assertCanSeeTableRecords([$payment->link])
            ->assertTableActionHidden('unlink', $payment->link)
            ->call('unlink', $payment->link->id)
            ->assertSet('showingRefusal', true)
            ->assertSee(__('conciliation.reconciliation.errors.link_already_removed'));

        $this->assertNotNull($payment->refresh()->link);
    }
}
