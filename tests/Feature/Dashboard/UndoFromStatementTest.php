<?php

namespace Tests\Feature\Dashboard;

use App\Actions\Conciliation\CreateMatchingAuthorization;
use App\Actions\Conciliation\LinkManually;
use App\Actions\Conciliation\ReopenSession;
use App\Enums\AuditAction;
use App\Enums\AuthorizationStatus;
use App\Enums\DifferenceTreatment;
use App\Enums\SessionStatus;
use App\Livewire\Dashboard\AuthorizationsTable;
use App\Models\AuditLog;
use App\Models\AuthorizationEntry;
use App\Models\PendingItem;
use App\Models\ReconciliationLink;
use App\Services\Reconciliation\AuthorizationStateCalculator;
use App\Services\Reconciliation\DifferenceDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class UndoFromStatementTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    public function test_undoing_the_second_of_three_instalments_reopens_the_authorization(): void
    {
        $this->travelTo('2026-10-09 10:00:00');
        $session = $this->sessionWithFiles(state: 'open');
        $purchase = $this->authorization($session, 'GRAFICA SUL', 90000, ['payment_condition' => '3x']);
        $this->payment($session, 'GRAFICA SUL', 30000, ['paid_on' => '2026-07-10']);
        $second = $this->payment($session, 'GRAFICA SUL', 30000, ['paid_on' => '2026-07-15']);
        $this->payment($session, 'GRAFICA SUL', 30000, ['paid_on' => '2026-07-20']);
        $this->reconcile($session);
        $operator = $this->operator();

        $this->assertSame(AuthorizationStatus::Reconciled, $purchase->refresh()->status());

        Livewire::actingAs($operator)
            ->test(AuthorizationsTable::class, ['scope' => 'reconciled'])
            ->assertCountTableRecords(1)
            ->assertSee(__('conciliation.dashboard.statement.undo'))
            ->callAction('undoLink', arguments: ['link' => $second->link->id])
            ->assertDispatched('reconciliation-changed')
            ->assertDispatched('banner-message', style: 'success', message: __('conciliation.reconciliation.actions.unlinked'))
            ->assertCountTableRecords(0);

        $this->assertNull($second->refresh()->link);
        $this->assertSame(AuthorizationStatus::Partial, $purchase->refresh()->status());
        $this->assertSame(30000, $purchase->balanceCents());
        $this->assertContains('p-'.$second->id, PendingItem::query()->pluck('id')->all());

        Livewire::actingAs($operator)
            ->test(AuthorizationsTable::class, ['scope' => 'open'])
            ->assertCanSeeTableRecords([$purchase])
            ->assertSee('R$ 300,00');

        $log = AuditLog::query()->where('action', AuditAction::LinkRemoved)->sole();

        $this->assertTrue($log->user->is($operator));
        $this->assertSame($second->id, $log->before['payment_entry_id']);
        $this->assertTrue($log->before['is_installment']);
        $this->assertSame(30000, $log->after['balance_cents']);
        $this->assertSame('2026-10-09 10:00:00', $log->created_at->format('Y-m-d H:i:s'));

        app(ReopenSession::class)->handle($operator, $session);
        $this->reconcile($session);

        $this->assertNull($second->refresh()->link);
    }

    public function test_a_link_already_undone_is_refused_in_the_dialog(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $this->authorization($session, 'PADARIA PERNAMBUCANA', 10000);
        $payment = $this->payment($session, 'PADARIA PERNAMBUCANA', 10000);
        $this->reconcile($session);
        $linkId = $payment->link->id;

        $screen = Livewire::actingAs($this->operator())->test(AuthorizationsTable::class, ['scope' => 'reconciled']);

        $this->unlinkPair($payment->link);

        $screen->call('undoLink', $linkId)
            ->assertSet('showingRefusal', true)
            ->assertSee(__('conciliation.reconciliation.errors.link_already_removed'))
            ->assertNotDispatched('reconciliation-changed')
            ->assertCountTableRecords(0);
    }

    public function test_a_payment_of_a_session_not_processed_cannot_be_undone(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $this->authorization($session, 'PADARIA PERNAMBUCANA', 10000);
        $payment = $this->payment($session, 'PADARIA PERNAMBUCANA', 10000);
        $this->reconcile($session);
        $linkId = $payment->link->id;

        $screen = Livewire::actingAs($this->operator())->test(AuthorizationsTable::class, ['scope' => 'reconciled']);

        $session->update(['status' => SessionStatus::Processing]);

        $screen->call('undoLink', $linkId)
            ->assertSet('showingRefusal', true)
            ->assertSee(__('conciliation.reconciliation.errors.session_not_processed'));

        $this->assertNotNull(ReconciliationLink::query()->find($linkId));
    }

    public function test_an_authorization_created_in_the_reconciliation_goes_away_with_its_only_payment(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $payment = $this->payment($session, 'POSTO ALFA', 7000);
        $this->reconcile($session);
        $link = app(CreateMatchingAuthorization::class)->handle($this->administrator(), $payment, 'Compra de emergência.');

        Livewire::actingAs($this->operator())
            ->test(AuthorizationsTable::class, ['scope' => 'reconciled'])
            ->assertCountTableRecords(1)
            ->mountAction('undoLink', arguments: ['link' => $link->id])
            ->assertMountedActionModalSee(__('conciliation.dashboard.statement.undo_created_body'))
            ->callMountedAction()
            ->assertCountTableRecords(0);

        $this->assertSame(0, AuthorizationEntry::query()->count());
        $this->assertSame(['p-'.$payment->id], PendingItem::query()->pluck('id')->all());
    }

    public function test_undoing_and_linking_right_after_leave_the_balance_the_links_give(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $purchase = $this->authorization($session, 'GRAFICA SUL', 90000, ['payment_condition' => '3x']);
        $first = $this->payment($session, 'GRAFICA SUL', 30000, ['paid_on' => '2026-07-10']);
        $this->payment($session, 'GRAFICA SUL', 30000, ['paid_on' => '2026-07-15']);
        $other = $this->payment($session, 'OUTRO FORNECEDOR', 20000);
        $this->reconcile($session);
        $operator = $this->operator();

        $screen = Livewire::actingAs($operator)->test(AuthorizationsTable::class, ['scope' => 'open']);

        app(LinkManually::class)->handle($operator, $purchase, [$other], new DifferenceDecision(DifferenceTreatment::StillOwed));

        $screen->call('undoLink', $first->link->id)->assertSet('showingRefusal', false);

        $stored = $purchase->refresh()->state->only(['links_count', 'paid_cents', 'balance_cents']);
        $recalculated = DB::transaction(fn () => app(AuthorizationStateCalculator::class)->recalculate($purchase->id))->only(['links_count', 'paid_cents', 'balance_cents']);

        $this->assertSame(['links_count' => 2, 'paid_cents' => 50000, 'balance_cents' => 40000], $stored);
        $this->assertSame($stored, $recalculated);
    }
}
