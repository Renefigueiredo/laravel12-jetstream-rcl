<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AuditAction;
use App\Enums\AuthorizationStatus;
use App\Enums\DifferenceTreatment;
use App\Enums\ImportSlot;
use App\Enums\JustificationCategory;
use App\Livewire\Dashboard\AuthorizationsTable;
use App\Livewire\Dashboard\DivergencesTable;
use App\Livewire\Dashboard\Show;
use App\Models\AuditLog;
use App\Models\AuthorizationEntry;
use App\Models\ExcludedOperationCode;
use App\Models\PendingItem;
use App\Services\Reconciliation\AuthorizationPanel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class DivergencesTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    protected function item(string $id): PendingItem
    {
        return PendingItem::query()->findOrFail($id);
    }

    public function test_tab_gathers_orphans_and_excess_of_every_session(): void
    {
        ExcludedOperationCode::factory()->create(['code' => '11024465']);

        $july = $this->sessionWithFiles('2026-07-01', state: 'open');
        $orphan = $this->payment($july, 'POSTO ALFA', 7000, ['card' => '0798', 'operation_name' => 'COMBUSTIVEL']);
        $this->authorization($july, 'GRAFICA SUL', 30000);
        $partial = $this->payment($july, 'GRAFICA SUL', 10000);
        $this->authorization($july, 'MERCADO BOM PRECO CENTRO', 20000);
        $doubtful = $this->payment($july, 'MERCADO BOM PRXYO', 20000);
        $this->payment($july, 'BANCO EMISSOR', 1350, ['operation_code' => '11024465']);
        $this->authorization($july, 'PADARIA PERNAMBUCANA', 10000);
        $this->payment($july, 'PADARIA PERNAMBUCANA', 10000);
        $this->reconcile($july);

        $august = $this->sessionWithFiles('2026-08-01', state: 'open');
        $this->authorization($august, 'RESTAURANTE SABOR', 50000);
        $excess = $this->payment($august, 'RESTAURANTE SABOR', 65000, [], ImportSlot::PaymentsSocial);
        $this->reconcile($august);

        $orphanItem = $this->item('p-'.$orphan->id);
        $excessItem = $this->item('s-'.$excess->suggestions()->sole()->id);

        $this->assertSame(2, app(AuthorizationPanel::class)->alerts()['divergences']);

        $screen = Livewire::actingAs($this->operator())->test(DivergencesTable::class);

        $screen->assertCountTableRecords(2)
            ->assertCanSeeTableRecords([$orphanItem, $excessItem])
            ->assertCanNotSeeTableRecords([
                $this->item('s-'.$partial->suggestions()->sole()->id),
                $this->item('s-'.$doubtful->suggestions()->sole()->id),
            ])
            ->assertSee(['POSTO ALFA', 'COMBUSTIVEL', 'R$ 70,00', $july->label(), 'RESTAURANTE SABOR', 'R$ 650,00', 'R$ 150,00', $august->label()])
            ->assertSee([__('conciliation.dashboard.divergences.types.unmatched_payment'), __('conciliation.dashboard.divergences.types.excess')])
            ->assertDontSee('BANCO EMISSOR');

        $screen->filterTable('classification', 'excess')
            ->assertCanSeeTableRecords([$excessItem])
            ->assertCanNotSeeTableRecords([$orphanItem])
            ->resetTableFilters()
            ->filterTable('unit', 'social')
            ->assertCanSeeTableRecords([$excessItem])
            ->assertCanNotSeeTableRecords([$orphanItem])
            ->resetTableFilters()
            ->filterTable('reconciliation_session_id', $july->id)
            ->assertCanSeeTableRecords([$orphanItem])
            ->assertCanNotSeeTableRecords([$excessItem])
            ->resetTableFilters()
            ->filterTable('payment_card', '0798')
            ->assertCanSeeTableRecords([$orphanItem])
            ->assertCanNotSeeTableRecords([$excessItem])
            ->resetTableFilters()
            ->filterTable('operation_code', '11022276')
            ->assertCountTableRecords(2)
            ->resetTableFilters()
            ->searchTable('RESTAURANTE')
            ->assertCanSeeTableRecords([$excessItem])
            ->assertCanNotSeeTableRecords([$orphanItem]);

        Livewire::actingAs($this->operator())
            ->withQueryParams(['aba' => 'divergencias'])
            ->test(Show::class)
            ->assertSeeLivewire(DivergencesTable::class)
            ->assertSee(trans_choice('conciliation.dashboard.alerts.divergences', 2, ['count' => 2]));
    }

    public function test_an_orphan_is_linked_to_an_open_authorization_of_an_earlier_session(): void
    {
        $this->travelTo('2026-10-09 11:30:00');
        $july = $this->sessionWithFiles('2026-07-01', state: 'open');
        $purchase = $this->authorization($july, 'AGENCIA DE VIAGENS', 45000);
        $this->reconcile($july);
        $august = $this->sessionWithFiles('2026-08-01', state: 'open');
        $sameMonth = $this->authorization($august, 'HOTEL CENTRAL', 80000);
        $payment = $this->payment($august, 'COMPANHIA AEREA', 40000);
        $this->reconcile($august);
        $operator = $this->operator();
        $item = $this->item('p-'.$payment->id);

        $screen = Livewire::actingAs($operator)->test(DivergencesTable::class);

        $screen->assertTableActionVisible('linkToAuthorization', $item)
            ->assertTableActionHidden('confirm', $item)
            ->assertTableActionHidden('createAuthorization', $item)
            ->mountTableAction('linkToAuthorization', $item)
            ->assertMountedActionModalSee(['AGENCIA DE VIAGENS', 'HOTEL CENTRAL'])
            ->setTableActionData(['authorization' => $purchase->id])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['treatment' => 'required']);

        $screen->setTableActionData(['authorization' => $purchase->id, 'treatment' => DifferenceTreatment::StillOwed->value])
            ->callMountedTableAction()
            ->assertDispatched('reconciliation-changed')
            ->assertDispatched('banner-message', style: 'success', message: __('conciliation.reconciliation.actions.linked'))
            ->assertCountTableRecords(0);

        $this->assertSame($purchase->id, $payment->refresh()->link->authorization_entry_id);
        $this->assertSame(5000, $purchase->refresh()->balanceCents());
        $this->assertSame(AuthorizationStatus::Open, $sameMonth->refresh()->status());

        $log = AuditLog::query()->where('action', AuditAction::ManualLinkCreated)->sole();

        $this->assertTrue($log->user->is($operator));
        $this->assertSame('2026-10-09 11:30:00', $log->created_at->format('Y-m-d H:i:s'));
    }

    public function test_an_open_authorization_is_linked_to_payments_from_the_list_of_the_panel(): void
    {
        $july = $this->sessionWithFiles('2026-07-01', state: 'open');
        $purchase = $this->authorization($july, 'AGENCIA DE VIAGENS', 45000);
        $ofJuly = $this->payment($july, 'HOTEL CENTRAL', 20000);
        $this->reconcile($july);
        $august = $this->sessionWithFiles('2026-08-01', state: 'open');
        $ofAugust = $this->payment($august, 'COMPANHIA AEREA', 45000);
        $this->reconcile($august);
        $june = $this->sessionWithFiles('2026-06-01', state: 'open');
        $earlier = $this->payment($june, 'PAGO ANTES DA SESSAO', 45000);
        $this->reconcile($june);
        $operator = $this->operator();

        $screen = Livewire::actingAs($operator)->test(AuthorizationsTable::class, ['scope' => 'open']);

        $screen->assertTableActionVisible('link', $purchase)
            ->mountTableAction('link', $purchase)
            ->assertMountedActionModalSee(['HOTEL CENTRAL', 'COMPANHIA AEREA', $july->label(), $august->label()])
            ->assertMountedActionModalDontSee('PAGO ANTES DA SESSAO')
            ->assertMountedActionModalSee([
                __('conciliation.reconciliation.picker.amount_from'),
                __('conciliation.reconciliation.picker.amount_until'),
                __('conciliation.reconciliation.picker.paid_from'),
                __('conciliation.reconciliation.picker.every_session'),
            ])
            ->setTableActionData(['payments' => [$ofJuly->id, $ofAugust->id], 'treatment' => DifferenceTreatment::Overpayment->value])
            ->callMountedTableAction()
            ->assertSet('showingRefusal', true)
            ->assertSee(__('conciliation.reconciliation.errors.payments_of_one_session'));

        Livewire::actingAs($operator)
            ->test(AuthorizationsTable::class, ['scope' => 'open'])
            ->mountTableAction('link', $purchase)
            ->setTableActionData(['payments' => [$ofAugust->id]])
            ->callMountedTableAction()
            ->assertDispatched('banner-message', style: 'success', message: __('conciliation.reconciliation.actions.linked'))
            ->assertCountTableRecords(0);

        $this->assertSame($purchase->id, $ofAugust->refresh()->link->authorization_entry_id);
        $this->assertNull($earlier->refresh()->link);
        $this->assertSame(AuthorizationStatus::Reconciled, $purchase->refresh()->status());
    }

    public function test_an_excess_is_confirmed_or_rejected_from_the_tab(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $withFreight = $this->authorization($session, 'RESTAURANTE SABOR', 50000);
        $freight = $this->payment($session, 'RESTAURANTE SABOR', 52000);
        $overpaid = $this->authorization($session, 'GRAFICA SUL', 20000);
        $tooMuch = $this->payment($session, 'GRAFICA SUL', 30000);
        $this->authorization($session, 'PAPELARIA CENTRAL', 10000);
        $rejected = $this->payment($session, 'PAPELARIA CENTRAL', 14000);
        $this->reconcile($session);
        $operator = $this->operator();

        $screen = Livewire::actingAs($operator)->test(DivergencesTable::class)->assertCountTableRecords(3);

        $screen->mountTableAction('confirm', $this->item('s-'.$freight->suggestions()->sole()->id))
            ->setTableActionData([
                'treatment' => DifferenceTreatment::AcceptedSurcharge->value,
                'category' => JustificationCategory::Freight->value,
                'justification' => 'Frete da entrega.',
            ])
            ->callMountedTableAction()
            ->assertDispatched('banner-message', style: 'success', message: __('conciliation.reconciliation.actions.confirmed'));

        $screen->mountTableAction('confirm', $this->item('s-'.$tooMuch->suggestions()->sole()->id))
            ->setTableActionData(['treatment' => DifferenceTreatment::Overpayment->value])
            ->callMountedTableAction();

        $rejectedItem = $this->item('s-'.$rejected->suggestions()->sole()->id);

        $screen->callTableAction('reject', $rejectedItem);

        $this->assertSame(DifferenceTreatment::AcceptedSurcharge, $freight->refresh()->link->treatment);
        $this->assertSame(AuthorizationStatus::Reconciled, $withFreight->refresh()->status());
        $this->assertSame(DifferenceTreatment::Overpayment, $tooMuch->refresh()->link->treatment);
        $this->assertSame(AuthorizationStatus::Reconciled, $overpaid->refresh()->status());
        $this->assertNull($rejected->refresh()->link);
        $this->assertSame(['p-'.$rejected->id], app(AuthorizationPanel::class)->divergences()->pluck('id')->all());
        $this->assertSame(1, app(AuthorizationPanel::class)->alerts()['overpaid']);

        $this->assertSame(2, AuditLog::query()->where('action', AuditAction::SuggestionConfirmed)->where('user_id', $operator->id)->count());
        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::SuggestionRejected)->where('user_id', $operator->id)->count());
    }

    public function test_only_an_administrator_creates_the_authorization_from_the_tab(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $payment = $this->payment($session, 'POSTO ALFA', 7000);
        $this->reconcile($session);
        $item = $this->item('p-'.$payment->id);
        $administrator = $this->administrator();

        Livewire::actingAs($this->operator())
            ->test(DivergencesTable::class)
            ->assertTableActionHidden('createAuthorization', $item);

        Livewire::actingAs($administrator)
            ->test(DivergencesTable::class)
            ->assertTableActionVisible('createAuthorization', $item)
            ->mountTableAction('createAuthorization', $item)
            ->setTableActionData(['justification' => 'Abastecimento de emergência.'])
            ->callMountedTableAction()
            ->assertCountTableRecords(0);

        $this->assertTrue(AuthorizationEntry::query()->sole()->isCreatedInReconciliation());
        $this->assertTrue(AuditLog::query()->where('action', AuditAction::AuthorizationCreatedInReconciliation)->sole()->user->is($administrator));
    }
}
