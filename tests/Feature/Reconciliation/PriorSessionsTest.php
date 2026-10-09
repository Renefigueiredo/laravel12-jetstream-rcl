<?php

namespace Tests\Feature\Reconciliation;

use App\Enums\AuthorizationStatus;
use App\Enums\SkipReason;
use App\Models\PendingItem;
use App\Models\ReconciliationSkip;
use App\Models\ReconciliationSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class PriorSessionsTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    public function test_open_authorization_of_an_earlier_session_is_paid_by_the_next_one(): void
    {
        $july = $this->sessionWithFiles('2026-07-01', state: 'open');
        $card = $this->authorization($july, 'MERCADO LIVRE', 32000, ['payment_method' => 'Cartão de crédito', 'card' => '0798']);
        $this->reconcile($july);

        $this->assertSame('unmatched_authorization', PendingItem::query()->sole()->classification);

        $august = $this->sessionWithFiles('2026-08-01', state: 'open');
        $invoice = $this->payment($august, 'MERCADO LIVRE', 32000, ['card' => '0798']);
        $run = $this->reconcile($august);

        $link = $invoice->link;

        $this->assertTrue($link->authorization->is($card));
        $this->assertSame($august->id, $link->run->reconciliation_session_id);
        $this->assertSame($july->id, $link->authorization->reconciliation_session_id);
        $this->assertSame(AuthorizationStatus::Reconciled, $card->refresh()->status());
        $this->assertSame(1, $run->totals['prior_authorizations_linked']);
        $this->assertSame(0, PendingItem::query()->count());
    }

    public function test_only_processed_sessions_inside_the_window_are_read(): void
    {
        $tooOld = $this->sessionWithFiles('2026-04-01');
        $atTheEdge = $this->sessionWithFiles('2026-05-01');
        $notProcessed = $this->sessionWithFiles('2026-07-01', state: 'open');
        $later = $this->sessionWithFiles('2026-09-01');

        $old = $this->authorization($tooOld, 'FORNECEDOR ABRIL', 10000);
        $edge = $this->authorization($atTheEdge, 'FORNECEDOR MAIO', 20000);
        $open = $this->authorization($notProcessed, 'FORNECEDOR JULHO', 30000);
        $future = $this->authorization($later, 'FORNECEDOR SETEMBRO', 40000);

        $august = $this->sessionWithFiles('2026-08-01', state: 'open');
        $forOld = $this->payment($august, 'FORNECEDOR ABRIL', 10000);
        $forEdge = $this->payment($august, 'FORNECEDOR MAIO', 20000);
        $forOpen = $this->payment($august, 'FORNECEDOR JULHO', 30000);
        $forFuture = $this->payment($august, 'FORNECEDOR SETEMBRO', 40000);

        $this->reconcile($august);

        $this->assertNull($forOld->link);
        $this->assertTrue($forEdge->link->authorization->is($edge));
        $this->assertNull($forOpen->link);
        $this->assertNull($forFuture->link);
        $this->assertSame(AuthorizationStatus::Open, $old->refresh()->status());
        $this->assertSame(AuthorizationStatus::Open, $open->refresh()->status());
        $this->assertSame(AuthorizationStatus::Open, $future->refresh()->status());
    }

    public function test_sessions_run_out_of_order_catch_up_after_a_new_run(): void
    {
        $july = $this->sessionWithFiles('2026-07-01', state: 'open');
        $authorization = $this->authorization($july, 'MERCADO LIVRE', 32000);
        $august = $this->sessionWithFiles('2026-08-01', state: 'open');
        $payment = $this->payment($august, 'MERCADO LIVRE', 32000);

        $this->reconcile($august);

        $this->assertNull($payment->link);

        $this->reconcile($july);
        $this->reconcile($august);

        $this->assertTrue($payment->refresh()->link->authorization->is($authorization));
    }

    public function test_tie_between_a_session_authorization_and_an_earlier_one_is_left_to_the_operator(): void
    {
        $july = $this->sessionWithFiles('2026-07-01');
        $earlier = $this->authorization($july, 'PADARIA PERNAMBUCANA', 50000);
        $august = $this->sessionWithFiles('2026-08-01', state: 'open');
        $current = $this->authorization($august, 'PADARIA PERNAMBUCANA', 50000);
        $payment = $this->payment($august, 'PADARIA PERNAMBUCANA', 50000);

        $this->reconcile($august);

        $this->assertNull($payment->link);

        $suggestions = ReconciliationSuggestion::query()->orderBy('authorization_entry_id')->get();

        $this->assertSame([$earlier->id, $current->id], $suggestions->pluck('authorization_entry_id')->all());
        $this->assertSame([true, true], $suggestions->pluck('is_tie')->all());
        $this->assertSame(
            [$august->id, $august->id],
            PendingItem::query()->where('kind', 'suggestion')->pluck('reconciliation_session_id')->all(),
        );
    }

    public function test_earlier_authorization_without_suggestion_stays_in_its_own_session(): void
    {
        $july = $this->sessionWithFiles('2026-07-01');
        $this->runFor($july);
        $earlier = $this->authorization($july, 'SERRALHERIA UNIAO', 77700);
        $august = $this->sessionWithFiles('2026-08-01', state: 'open');
        $this->payment($august, 'POSTO ALFA', 9900);

        $this->reconcile($august);

        $this->assertSame(
            ['a-'.$earlier->id],
            PendingItem::query()->where('reconciliation_session_id', $july->id)->pluck('id')->all(),
        );
        $this->assertSame(
            ['unmatched_payment'],
            PendingItem::query()->where('reconciliation_session_id', $august->id)->pluck('classification')->all(),
        );
    }

    public function test_an_entry_already_present_in_another_period_is_skipped_as_duplicate(): void
    {
        $july = $this->sessionWithFiles('2026-07-01');
        $original = $this->payment($july, 'PADARIA PERNAMBUCANA', 10000, ['identity_key' => 'OB1|11022276|MV1']);
        $originalAuthorization = $this->authorization($july, 'PAPELARIA CENTRAL', 20000, ['identity_key' => str_repeat('a', 64)]);

        $august = $this->sessionWithFiles('2026-08-01', state: 'open');
        $copy = $this->payment($august, 'PADARIA PERNAMBUCANA', 10000, ['identity_key' => 'OB1|11022276|MV1']);
        $copyAuthorization = $this->authorization($august, 'PAPELARIA CENTRAL', 20000, ['identity_key' => str_repeat('a', 64)]);
        $repeatedPurchase = $this->authorization($august, 'PAPELARIA CENTRAL', 20000);

        $this->reconcile($august);

        $skips = ReconciliationSkip::query()->get();

        $this->assertCount(2, $skips);
        $this->assertSame([SkipReason::DuplicateOfOtherPeriod], $skips->pluck('reason')->unique()->values()->all());
        $this->assertSame([$july->id], $skips->pluck('original_session_id')->unique()->values()->all());
        $this->assertTrue($skips->contains('payment_entry_id', $copy->id));
        $this->assertTrue($skips->contains('authorization_entry_id', $copyAuthorization->id));
        $this->assertFalse($skips->contains('authorization_entry_id', $repeatedPurchase->id));
        $this->assertFalse(PendingItem::query()->whereIn('id', ['p-'.$copy->id, 'a-'.$copyAuthorization->id])->exists());
        $this->assertTrue(PendingItem::query()->where('id', 'a-'.$repeatedPurchase->id)->exists());
        $this->assertNull($original->link);
        $this->assertSame(AuthorizationStatus::Open, $originalAuthorization->refresh()->status());
    }
}
