<?php

namespace Tests\Feature\Dashboard;

use App\Actions\Conciliation\ConfirmSuggestion;
use App\Actions\Conciliation\RemoveLink;
use App\Actions\Conciliation\ReopenSession;
use App\Actions\Conciliation\SaveInstallmentPlan;
use App\Enums\AuthorizationStatus;
use App\Enums\DifferenceTreatment;
use App\Enums\MatchClassification;
use App\Livewire\Reconciliation\PendingTable;
use App\Models\AuthorizationEntry;
use App\Services\Reconciliation\DifferenceDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class InstallmentPlanEngineTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    /**
     * @param  list<int>  $amounts
     */
    protected function plan(AuthorizationEntry $authorization, array $amounts): void
    {
        app(SaveInstallmentPlan::class)->handle(
            $this->operator(),
            $authorization,
            array_map(fn (int $cents): array => ['amount_cents' => $cents], $amounts),
        );
    }

    public function test_unequal_instalments_are_linked_in_three_sessions(): void
    {
        $july = $this->sessionWithFiles('2026-07-01', state: 'open');
        $purchase = $this->authorization($july, 'GRAFICA SUL', 100000, ['payment_condition' => 'A vista']);
        $entry = $this->payment($july, 'GRAFICA SUL', 40000);
        $this->reconcile($july);

        $this->assertNull($entry->link);

        app(ConfirmSuggestion::class)->handle($this->operator(), $entry->suggestions()->sole(), new DifferenceDecision(DifferenceTreatment::StillOwed));
        $this->plan($purchase, [40000, 30000, 30000]);

        $august = $this->sessionWithFiles('2026-08-01', state: 'open');
        $second = $this->payment($august, 'GRAFICA SUL', 30000);
        $repeatedEntry = $this->payment($august, 'GRAFICA SUL', 40000);
        $this->reconcile($august);

        $this->assertTrue($second->link->is_installment);
        $this->assertSame(MatchClassification::Installment, $second->link->engine_classification);
        $this->assertSame(DifferenceTreatment::StillOwed, $second->link->treatment);
        $this->assertNull($repeatedEntry->link);
        $this->assertSame(MatchClassification::Excess, $repeatedEntry->suggestions()->sole()->classification);
        $this->assertSame(30000, $purchase->refresh()->balanceCents());

        $september = $this->sessionWithFiles('2026-09-01', state: 'open');
        $third = $this->payment($september, 'GRAFICA SUL', 30000);
        $this->reconcile($september);

        $this->assertSame($purchase->id, $third->link->authorization_entry_id);
        $this->assertSame(AuthorizationStatus::Reconciled, $purchase->refresh()->status());
        $this->assertSame(100000, $purchase->state->paid_cents);
    }

    public function test_the_plan_prevails_over_the_condition_of_the_spreadsheet(): void
    {
        $july = $this->processedSession('2026-07-01');
        $purchase = $this->authorization($july, 'GRAFICA SUL', 100000, ['payment_condition' => '2x']);
        $this->plan($purchase, [40000, 30000, 30000]);

        $august = $this->sessionWithFiles('2026-08-01', state: 'open');
        $half = $this->payment($august, 'GRAFICA SUL', 50000);
        $entry = $this->payment($august, 'GRAFICA SUL', 40000);
        $this->reconcile($august);

        $this->assertNull($half->link);
        $this->assertSame(MatchClassification::Partial, $half->suggestions()->sole()->classification);
        $this->assertTrue($entry->link->is_installment);

        Livewire::actingAs($this->operator())
            ->test(PendingTable::class, ['sessionId' => $august->id])
            ->assertSee(__('conciliation.reconciliation.warnings.not_the_installment'));
    }

    public function test_two_equal_instalments_in_one_session_take_one_payment_each(): void
    {
        $july = $this->processedSession('2026-07-01');
        $purchase = $this->authorization($july, 'GRAFICA SUL', 100000);
        $this->plan($purchase, [40000, 30000, 30000]);

        $august = $this->sessionWithFiles('2026-08-01', state: 'open');
        $payments = [
            $this->payment($august, 'GRAFICA SUL', 30000, ['paid_on' => '2026-08-05']),
            $this->payment($august, 'GRAFICA SUL', 30000, ['paid_on' => '2026-08-10']),
            $this->payment($august, 'GRAFICA SUL', 30000, ['paid_on' => '2026-08-15']),
        ];
        $this->reconcile($august);

        $this->assertNotNull($payments[0]->link);
        $this->assertNotNull($payments[1]->link);
        $this->assertNull($payments[2]->link);
        $this->assertSame(40000, $purchase->refresh()->balanceCents());
    }

    public function test_changing_the_plan_keeps_the_links_and_applies_to_the_next_run(): void
    {
        $july = $this->processedSession('2026-07-01');
        $purchase = $this->authorization($july, 'GRAFICA SUL', 100000);
        $this->plan($purchase, [40000, 30000, 30000]);

        $august = $this->sessionWithFiles('2026-08-01', state: 'open');
        $entry = $this->payment($august, 'GRAFICA SUL', 40000);
        $this->reconcile($august);

        $this->plan($purchase, [40000, 20000, 40000]);

        $this->assertSame($purchase->id, $entry->refresh()->link->authorization_entry_id);
        $this->assertSame(60000, $purchase->refresh()->balanceCents());

        $september = $this->sessionWithFiles('2026-09-01', state: 'open');
        $old = $this->payment($september, 'GRAFICA SUL', 30000);
        $new = $this->payment($september, 'GRAFICA SUL', 20000);
        $otherNew = $this->payment($september, 'GRAFICA SUL', 40000);
        $this->reconcile($september);

        $this->assertNull($old->link);
        $this->assertTrue($new->link->is_installment);
        $this->assertNotNull($otherNew->link);
        $this->assertSame(AuthorizationStatus::Reconciled, $purchase->refresh()->status());
    }

    public function test_an_unlinked_plan_instalment_does_not_come_back_on_its_own(): void
    {
        $july = $this->processedSession('2026-07-01');
        $purchase = $this->authorization($july, 'GRAFICA SUL', 100000);
        $this->plan($purchase, [40000, 30000, 30000]);

        $august = $this->sessionWithFiles('2026-08-01', state: 'open');
        $entry = $this->payment($august, 'GRAFICA SUL', 40000);
        $this->reconcile($august);
        $operator = $this->operator();

        app(RemoveLink::class)->handle($operator, $entry->link);
        app(ReopenSession::class)->handle($operator, $august);
        $this->reconcile($august);

        $this->assertNull($entry->refresh()->link);
        $this->assertSame(100000, $purchase->refresh()->balanceCents());
    }
}
