<?php

namespace Tests\Feature\Reconciliation;

use App\Actions\Conciliation\ConfirmSuggestion;
use App\Actions\Conciliation\RemoveLink;
use App\Actions\Conciliation\ReopenSession;
use App\Enums\AuthorizationStatus;
use App\Enums\DifferenceTreatment;
use App\Enums\DifferenceType;
use App\Enums\LinkOrigin;
use App\Enums\MatchClassification;
use App\Enums\PendingItemKind;
use App\Livewire\Reconciliation\LinksTable;
use App\Livewire\Reconciliation\PendingTable;
use App\Models\PendingItem;
use App\Models\ReconciliationSuggestion;
use App\Services\Reconciliation\DifferenceDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class InstallmentsAcrossSessionsTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    public function test_three_instalments_are_linked_in_three_sessions(): void
    {
        $july = $this->sessionWithFiles('2026-07-01', state: 'open');
        $purchase = $this->authorization($july, 'GRAFICA SUL', 90000, ['payment_condition' => '3x']);
        $first = $this->payment($july, 'GRAFICA SUL', 30000);
        $run = $this->reconcile($july);

        $link = $first->link;

        $this->assertTrue($link->is_installment);
        $this->assertSame(LinkOrigin::Automatic, $link->origin);
        $this->assertSame(MatchClassification::Installment, $link->engine_classification);
        $this->assertSame(100, $link->score);
        $this->assertSame(DifferenceType::Partial, $link->difference_type);
        $this->assertSame(DifferenceTreatment::StillOwed, $link->treatment);
        $this->assertNull($link->decided_by);
        $this->assertSame(AuthorizationStatus::Partial, $purchase->refresh()->status());
        $this->assertSame(60000, $purchase->balanceCents());
        $this->assertSame(1, $run->totals['installments']);
        $this->assertSame([PendingItemKind::OpenBalance], PendingItem::query()->where('reconciliation_session_id', $july->id)->get()->pluck('kind')->all());
        $this->assertSame(0, ReconciliationSuggestion::query()->count());

        $august = $this->sessionWithFiles('2026-08-01', state: 'open');
        $second = $this->payment($august, 'GRAFICA SUL', 30000);
        $this->reconcile($august);

        $this->assertTrue($second->link->is_installment);
        $this->assertSame(30000, $purchase->refresh()->balanceCents());

        $september = $this->sessionWithFiles('2026-09-01', state: 'open');
        $third = $this->payment($september, 'GRAFICA SUL', 30000);
        $this->reconcile($september);

        $this->assertSame($purchase->id, $third->link->authorization_entry_id);
        $this->assertSame(DifferenceType::Exact, $third->link->difference_type);
        $this->assertSame(AuthorizationStatus::Reconciled, $purchase->refresh()->status());
        $this->assertSame(3, $purchase->state->links_count);
        $this->assertSame(90000, $purchase->state->paid_cents);
    }

    public function test_a_cash_purchase_paid_in_parts_needs_only_the_first_confirmation(): void
    {
        $july = $this->sessionWithFiles('2026-07-01', state: 'open');
        $purchase = $this->authorization($july, 'GRAFICA SUL', 100000, ['payment_condition' => 'A vista']);
        $first = $this->payment($july, 'GRAFICA SUL', 10000);
        $this->reconcile($july);

        $suggestion = $first->suggestions()->sole();

        $this->assertNull($first->link);
        $this->assertSame(MatchClassification::Partial, $suggestion->classification);

        app(ConfirmSuggestion::class)->handle($this->operator(), $suggestion, new DifferenceDecision(DifferenceTreatment::StillOwed));

        $august = $this->sessionWithFiles('2026-08-01', state: 'open');
        $second = $this->payment($august, 'GRAFICA SUL', 10000);
        $other = $this->payment($august, 'GRAFICA SUL', 25000);
        $this->reconcile($august);

        $this->assertTrue($second->link->is_installment);
        $this->assertSame($purchase->id, $second->link->authorization_entry_id);
        $this->assertNull($other->link);
        $this->assertSame(MatchClassification::Partial, $other->suggestions()->sole()->classification);
        $this->assertSame(80000, $purchase->refresh()->balanceCents());
    }

    public function test_condition_is_only_a_hint(): void
    {
        $july = $this->sessionWithFiles('2026-07-01', state: 'open');
        $paidAtOnce = $this->authorization($july, 'GRAFICA SUL', 90000, ['payment_condition' => '3x']);
        $whole = $this->payment($july, 'GRAFICA SUL', 90000);
        $otherAmount = $this->authorization($july, 'PAPELARIA CENTRAL', 90000, ['payment_condition' => '3x']);
        $half = $this->payment($july, 'PAPELARIA CENTRAL', 45000);
        $unknown = $this->authorization($july, 'POSTO ALFA', 90000, ['payment_condition' => '488,02']);
        $part = $this->payment($july, 'POSTO ALFA', 30000);
        $this->reconcile($july);

        $this->assertFalse($whole->link->is_installment);
        $this->assertSame(AuthorizationStatus::Reconciled, $paidAtOnce->refresh()->status());
        $this->assertNull($half->link);
        $this->assertSame(MatchClassification::Partial, $half->suggestions()->sole()->classification);
        $this->assertSame(AuthorizationStatus::Open, $otherAmount->refresh()->status());
        $this->assertNull($part->link);
        $this->assertSame(MatchClassification::Partial, $part->suggestions()->sole()->classification);
        $this->assertSame(AuthorizationStatus::Open, $unknown->refresh()->status());
    }

    public function test_lists_show_the_condition_the_foreseen_instalments_and_the_warning(): void
    {
        $july = $this->sessionWithFiles('2026-07-01', state: 'open');
        $this->authorization($july, 'GRAFICA SUL', 90000, ['payment_condition' => '3x']);
        $instalment = $this->payment($july, 'GRAFICA SUL', 30000);
        $this->authorization($july, 'PAPELARIA CENTRAL', 90000, ['payment_condition' => '30/60/90 dias']);
        $this->payment($july, 'PAPELARIA CENTRAL', 45000);
        $this->authorization($july, 'POSTO ALFA', 90000, ['payment_condition' => 'A vista']);
        $this->payment($july, 'POSTO ALFA', 45000);
        $this->reconcile($july);

        Livewire::actingAs($this->operator())
            ->test(PendingTable::class, ['sessionId' => $july->id])
            ->assertSee('3x (3 parcelas previstas)')
            ->assertSee('30/60/90 dias (3 parcelas previstas)')
            ->assertSee(trans_choice('conciliation.reconciliation.columns.linked_payments', 1, ['count' => 1]))
            ->assertSee('R$ 600,00')
            ->assertSeeInOrder([__('conciliation.reconciliation.warnings.not_the_installment'), 'PAPELARIA CENTRAL'])
            ->assertSee('A vista')
            ->assertDontSee('A vista (');

        $this->assertSame(1, substr_count(
            Livewire::actingAs($this->operator())->test(PendingTable::class, ['sessionId' => $july->id])->html(),
            __('conciliation.reconciliation.warnings.not_the_installment'),
        ));

        Livewire::actingAs($this->operator())
            ->test(LinksTable::class, ['sessionId' => $july->id])
            ->assertCanSeeTableRecords([$instalment->link])
            ->assertSee(__('conciliation.reconciliation.link_origin.installment'))
            ->assertSee('3x (3 parcelas previstas)');
    }

    public function test_instalments_with_a_cent_left_over_settle_the_authorization(): void
    {
        $july = $this->sessionWithFiles('2026-07-01', state: 'open');
        $purchase = $this->authorization($july, 'GRAFICA SUL', 100000, ['payment_condition' => '30/60/90 dias']);
        $this->payment($july, 'GRAFICA SUL', 33333, ['paid_on' => '2026-07-10']);
        $this->payment($july, 'GRAFICA SUL', 33333, ['paid_on' => '2026-07-15']);
        $last = $this->payment($july, 'GRAFICA SUL', 33334, ['paid_on' => '2026-07-20']);
        $this->reconcile($july);

        $this->assertSame(AuthorizationStatus::Reconciled, $purchase->refresh()->status());
        $this->assertSame(3, $purchase->state->links_count);
        $this->assertSame(100000, $purchase->state->paid_cents);
        $this->assertSame(DifferenceType::Exact, $last->link->difference_type);
        $this->assertSame(0, PendingItem::query()->count());
    }

    public function test_an_authorization_receiving_instalments_is_read_beyond_the_window(): void
    {
        $january = $this->sessionWithFiles('2026-01-01', state: 'open');
        $purchase = $this->authorization($january, 'GRAFICA SUL', 90000, ['payment_condition' => '3x']);
        $untouched = $this->authorization($january, 'PAPELARIA CENTRAL', 90000, ['payment_condition' => '3x']);
        $this->payment($january, 'GRAFICA SUL', 30000);
        $this->reconcile($january);

        $august = $this->sessionWithFiles('2026-08-01', state: 'open');
        $late = $this->payment($august, 'GRAFICA SUL', 30000);
        $tooLate = $this->payment($august, 'PAPELARIA CENTRAL', 30000);
        $this->reconcile($august);

        $this->assertSame($purchase->id, $late->link->authorization_entry_id);
        $this->assertTrue($late->link->is_installment);
        $this->assertNull($tooLate->link);
        $this->assertSame(0, $tooLate->suggestions()->count());
        $this->assertSame(AuthorizationStatus::Open, $untouched->refresh()->status());
    }

    public function test_two_authorizations_served_by_one_payment_wait_for_the_operator(): void
    {
        $july = $this->sessionWithFiles('2026-07-01', state: 'open');
        $this->authorization($july, 'GRAFICA SUL', 90000, ['payment_condition' => '3x', 'request' => 'CARTAZES']);
        $this->authorization($july, 'GRAFICA SUL', 90000, ['payment_condition' => '3x', 'request' => 'FOLDERS']);
        $payment = $this->payment($july, 'GRAFICA SUL', 30000);
        $this->reconcile($july);

        $this->assertNull($payment->link);
        $this->assertSame(2, $payment->suggestions()->count());
    }

    public function test_an_unlinked_instalment_does_not_come_back_on_its_own(): void
    {
        $july = $this->sessionWithFiles('2026-07-01', state: 'open');
        $purchase = $this->authorization($july, 'GRAFICA SUL', 90000, ['payment_condition' => '3x']);
        $payment = $this->payment($july, 'GRAFICA SUL', 30000);
        $this->reconcile($july);
        $operator = $this->operator();

        app(RemoveLink::class)->handle($operator, $payment->link);

        $this->assertSame(90000, $purchase->refresh()->balanceCents());
        $this->assertSame(AuthorizationStatus::Open, $purchase->status());

        app(ReopenSession::class)->handle($operator, $july);
        $this->reconcile($july);

        $this->assertNull($payment->refresh()->link);
        $this->assertSame(0, $payment->suggestions()->count());
    }

    public function test_instalment_links_never_pass_the_authorized_amount(): void
    {
        $july = $this->sessionWithFiles('2026-07-01', state: 'open');
        $purchase = $this->authorization($july, 'GRAFICA SUL', 90000, ['payment_condition' => '3x']);

        foreach (range(10, 14) as $day) {
            $this->payment($july, 'GRAFICA SUL', 30000, ['paid_on' => '2026-07-'.$day]);
        }

        $this->reconcile($july);

        $this->assertSame(90000, $purchase->refresh()->state->paid_cents);
        $this->assertSame(3, $purchase->state->links_count);
        $this->assertSame(AuthorizationStatus::Reconciled, $purchase->status());
    }
}
