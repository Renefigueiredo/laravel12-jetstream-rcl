<?php

namespace Tests\Feature\Reconciliation;

use App\Actions\Conciliation\ActionRefusedException;
use App\Actions\Conciliation\ConfirmSuggestion;
use App\Actions\Conciliation\LinkManually;
use App\Actions\Conciliation\RemoveLink;
use App\Actions\Conciliation\ReopenSession;
use App\Enums\AuditAction;
use App\Enums\AuthorizationStatus;
use App\Enums\DifferenceTreatment;
use App\Enums\DifferenceType;
use App\Enums\LinkOrigin;
use App\Enums\PairBlockReason;
use App\Enums\SuggestionStatus;
use App\Models\AuditLog;
use App\Models\ExcludedOperationCode;
use App\Models\PendingItem;
use App\Models\ReconciliationLink;
use App\Models\ReconciliationPairBlock;
use App\Models\ReconciliationSuggestion;
use App\Services\Reconciliation\DifferenceDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class ManualLinkTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    public function test_an_unmatched_authorization_is_linked_to_an_unmatched_payment(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $authorization = $this->authorization($session, 'SERRALHERIA UNIAO', 77700, ['authorized_on' => '2026-07-25']);
        $payment = $this->payment($session, 'JOSE FERREIRA ME', 77700, ['paid_on' => '2026-07-20']);
        $this->reconcile($session);
        $operator = $this->operator();

        [$link] = app(LinkManually::class)->handle($operator, $authorization, [$payment]);

        $this->assertSame(LinkOrigin::Manual, $link->origin);
        $this->assertSame(DifferenceType::Exact, $link->difference_type);
        $this->assertNull($link->engine_classification);
        $this->assertTrue($link->paid_before_authorization);
        $this->assertTrue($link->decider->is($operator));
        $this->assertSame(AuthorizationStatus::Reconciled, $authorization->refresh()->status());
        $this->assertSame(0, PendingItem::query()->count());

        $log = AuditLog::query()->where('action', AuditAction::ManualLinkCreated)->sole();

        $this->assertTrue($log->user->is($operator));
        $this->assertSame(77700, $log->before['balance_cents']);
        $this->assertSame(0, $log->after['balance_cents']);
    }

    public function test_a_marketplace_order_paid_to_several_sellers_is_linked_at_once(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $order = $this->authorization($session, 'MERCADO LIVRE BRASIL EBAZAR', 66313, ['payment_method' => 'Cartão de crédito', 'card' => '0798']);
        $invoice = ['card' => '0798', 'species' => 'FATURA CARTAO 0798'];
        $sellers = [
            $this->payment($session, 'PAPELARIA DOIS IRMAOS', 20000, [...$invoice, 'paid_on' => '2026-07-06']),
            $this->payment($session, 'DISTRIBUIDORA ALFA', 30000, [...$invoice, 'paid_on' => '2026-07-07']),
            $this->payment($session, 'KALUNGA COMERCIO', 16313, [...$invoice, 'paid_on' => '2026-07-08']),
        ];
        $this->reconcile($session);

        $links = app(LinkManually::class)->handle($this->operator(), $order, array_reverse($sellers));

        $this->assertCount(3, $links);
        $this->assertSame(
            [DifferenceTreatment::StillOwed, DifferenceTreatment::StillOwed, null],
            array_map(fn (ReconciliationLink $link): ?DifferenceTreatment => $link->treatment, $links),
        );
        $this->assertSame(DifferenceType::Exact, $links[2]->difference_type);
        $this->assertSame(AuthorizationStatus::Reconciled, $order->refresh()->status());
        $this->assertSame(3, $order->state->links_count);
        $this->assertSame(66313, $order->state->paid_cents);
        $this->assertSame(3, AuditLog::query()->where('action', AuditAction::ManualLinkCreated)->count());
        $this->assertSame(0, PendingItem::query()->count());
    }

    public function test_several_payments_that_do_not_add_up_need_a_decision_on_the_last_one(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $order = $this->authorization($session, 'MERCADO LIVRE BRASIL EBAZAR', 100000);
        $payments = [
            $this->payment($session, 'VENDEDOR UM', 40000, ['paid_on' => '2026-07-06']),
            $this->payment($session, 'VENDEDOR DOIS', 50000, ['paid_on' => '2026-07-07']),
        ];
        $this->reconcile($session);

        try {
            app(LinkManually::class)->handle($this->operator(), $order, $payments);
            $this->fail('A missing amount without a decision should have been refused.');
        } catch (ActionRefusedException $exception) {
            $this->assertSame(__('conciliation.reconciliation.errors.treatment_required.partial'), $exception->getMessage());
            $this->assertSame(0, ReconciliationLink::query()->count());
        }

        app(LinkManually::class)->handle($this->operator(), $order, $payments, new DifferenceDecision(DifferenceTreatment::StillOwed));

        $this->assertSame(10000, $order->refresh()->balanceCents());
        $this->assertSame(AuthorizationStatus::Partial, $order->status());
    }

    public function test_payments_that_pass_the_balance_before_the_last_one_are_refused(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $order = $this->authorization($session, 'MERCADO LIVRE BRASIL EBAZAR', 50000);
        $payments = [
            $this->payment($session, 'VENDEDOR UM', 50000, ['paid_on' => '2026-07-06']),
            $this->payment($session, 'VENDEDOR DOIS', 10000, ['paid_on' => '2026-07-07']),
        ];
        $this->reconcile($session);

        try {
            app(LinkManually::class)->handle($this->operator(), $order, $payments, new DifferenceDecision(DifferenceTreatment::Overpayment));
            $this->fail('The selection should have been refused.');
        } catch (ActionRefusedException $exception) {
            $this->assertSame(__('conciliation.reconciliation.errors.too_many_payments'), $exception->getMessage());
        }

        $this->assertSame(0, ReconciliationLink::query()->count());
        $this->assertNull($order->refresh()->state);
    }

    public function test_payments_that_cannot_be_linked_are_refused(): void
    {
        ExcludedOperationCode::factory()->create(['code' => '20150652']);
        $session = $this->sessionWithFiles(state: 'open');
        $authorization = $this->authorization($session, 'SERRALHERIA UNIAO', 77700);
        $taken = $this->authorization($session, 'PADARIA PERNAMBUCANA', 10000);
        $linked = $this->payment($session, 'PADARIA PERNAMBUCANA', 10000);
        $excluded = $this->payment($session, 'FOLHA', 77700, ['operation_code' => '20150652']);
        $other = $this->sessionWithFiles('2026-08-01', state: 'open');
        $elsewhere = $this->payment($other, 'JOSE FERREIRA ME', 77700);
        $free = $this->payment($session, 'JOSE FERREIRA ME', 77700);
        $this->reconcile($session);
        $this->reconcile($other);

        $refused = [
            'payment_already_linked' => [$linked],
            'payment_skipped' => [$excluded],
            'payments_of_one_session' => [$free, $elsewhere],
            'no_payment_selected' => [],
        ];

        foreach ($refused as $message => $payments) {
            try {
                app(LinkManually::class)->handle($this->operator(), $authorization, $payments);
                $this->fail('The link should have been refused: '.$message);
            } catch (ActionRefusedException $exception) {
                $this->assertSame(__('conciliation.reconciliation.errors.'.$message), $exception->getMessage());
            }
        }

        try {
            app(LinkManually::class)->handle($this->operator(), $taken, [$free]);
            $this->fail('A settled authorization should not receive payments.');
        } catch (ActionRefusedException $exception) {
            $this->assertSame(__('conciliation.reconciliation.errors.authorization_settled'), $exception->getMessage());
        }

        $this->assertSame(1, ReconciliationLink::query()->count());
    }

    public function test_a_payment_can_be_linked_to_an_open_authorization_of_an_earlier_session_in_the_window(): void
    {
        $july = $this->sessionWithFiles('2026-07-01', state: 'open');
        $earlier = $this->authorization($july, 'SERRALHERIA UNIAO', 77700);
        $this->reconcile($july);
        $old = $this->sessionWithFiles('2026-03-01', state: 'open');
        $tooOld = $this->authorization($old, 'VIDRACARIA CRISTAL', 12300);
        $this->reconcile($old);
        $august = $this->sessionWithFiles('2026-08-01', state: 'open');
        $payment = $this->payment($august, 'JOSE FERREIRA ME', 77700);
        $another = $this->payment($august, 'MARIA VIDROS', 12300);
        $this->reconcile($august);

        [$link] = app(LinkManually::class)->handle($this->operator(), $earlier, [$payment]);

        $this->assertSame($august->id, $link->run->reconciliation_session_id);
        $this->assertSame(AuthorizationStatus::Reconciled, $earlier->refresh()->status());

        $this->expectException(ActionRefusedException::class);

        app(LinkManually::class)->handle($this->operator(), $tooOld, [$another]);
    }

    public function test_unlinking_an_automatic_pair_returns_both_to_the_pending_lists(): void
    {
        $this->travelTo('2026-08-10 17:20:30');
        $session = $this->sessionWithFiles(state: 'open');
        $authorization = $this->authorization($session, 'PADARIA PERNAMBUCANA', 10000);
        $payment = $this->payment($session, 'PADARIA PERNAMBUCANA', 10000);
        $this->reconcile($session);
        $operator = $this->operator();
        $link = $payment->link;

        app(RemoveLink::class)->handle($operator, $link);

        $this->assertModelMissing($link);
        $this->assertSame(AuthorizationStatus::Open, $authorization->refresh()->status());
        $this->assertEqualsCanonicalizing(['a-'.$authorization->id, 'p-'.$payment->id], PendingItem::query()->pluck('id')->all());
        $this->assertSame(PairBlockReason::Unlinked, ReconciliationPairBlock::query()->sole()->reason);

        $log = AuditLog::query()->where('action', AuditAction::LinkRemoved)->sole();

        $this->assertTrue($log->user->is($operator));
        $this->assertSame($link->id, $log->auditable_id);
        $this->assertSame('automatic', $log->before['origin']);
        $this->assertSame(100, $log->before['score']);
        $this->assertSame('automatic', $log->before['engine_classification']);
        $this->assertNull($log->before['decided_by']);
        $this->assertSame(['balance_cents' => 10000, 'status' => 'open'], $log->after);
        $this->assertSame('2026-08-10 17:20:30', $log->created_at->format('Y-m-d H:i:s'));

        app(ReopenSession::class)->handle($operator, $session);
        $this->reconcile($session);

        $this->assertSame(0, ReconciliationLink::query()->count());
        $this->assertSame(0, ReconciliationSuggestion::query()->count());
    }

    public function test_unlinking_a_manual_pair_records_who_had_linked_it(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $authorization = $this->authorization($session, 'SERRALHERIA UNIAO', 77700);
        $payment = $this->payment($session, 'JOSE FERREIRA ME', 77700);
        $this->reconcile($session);
        $linker = $this->operator();
        [$link] = app(LinkManually::class)->handle($linker, $authorization, [$payment]);

        app(RemoveLink::class)->handle($this->operator(), $link);

        $log = AuditLog::query()->where('action', AuditAction::LinkRemoved)->sole();

        $this->assertSame('manual', $log->before['origin']);
        $this->assertSame($linker->id, $log->before['decided_by']);
        $this->assertSame($linker->name, $log->before['decided_by_name']);

        $this->expectException(ActionRefusedException::class);

        app(RemoveLink::class)->handle($this->operator(), $link);
    }

    public function test_unlinking_one_of_two_payments_recalculates_from_the_other(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $authorization = $this->authorization($session, 'MERCADO LIVRE BRASIL EBAZAR', 50000);
        $first = $this->payment($session, 'VENDEDOR UM', 20000, ['paid_on' => '2026-07-06']);
        $second = $this->payment($session, 'VENDEDOR DOIS', 30000, ['paid_on' => '2026-07-07']);
        $this->reconcile($session);
        [, $last] = app(LinkManually::class)->handle($this->operator(), $authorization, [$first, $second]);

        app(RemoveLink::class)->handle($this->operator(), $last);

        $this->assertSame(30000, $authorization->refresh()->balanceCents());
        $this->assertSame(AuthorizationStatus::Partial, $authorization->status());
        $this->assertSame(1, $authorization->state->links_count);
    }

    public function test_unlinking_brings_back_the_suggestions_the_link_had_put_aside(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $chosen = $this->authorization($session, 'PADARIA PERNAMBUCANA', 29000);
        $other = $this->authorization($session, 'PADARIA PERNAMBUCANA', 29000);
        $payment = $this->payment($session, 'PADARIA PERNAMBUCANA', 29000);
        $this->reconcile($session);
        $link = app(ConfirmSuggestion::class)->handle($this->operator(), $chosen->suggestions()->sole());

        app(RemoveLink::class)->handle($this->operator(), $link);

        $this->assertSame(SuggestionStatus::Pending, $other->suggestions()->sole()->status);
        $this->assertSame(SuggestionStatus::Pending, $chosen->suggestions()->sole()->status);
        $this->assertNull($chosen->suggestions()->sole()->decided_by);
        $this->assertNull($payment->refresh()->link);
        $this->assertSame(0, ReconciliationPairBlock::query()->count());
        $this->assertEqualsCanonicalizing(
            ['s-'.$chosen->suggestions()->sole()->id, 's-'.$other->suggestions()->sole()->id],
            PendingItem::query()->where('kind', 'suggestion')->pluck('id')->all(),
        );
    }

    public function test_every_decision_leaves_an_audit_record(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $authorization = $this->authorization($session, 'SERRALHERIA UNIAO', 77700);
        $payment = $this->payment($session, 'JOSE FERREIRA ME', 77700);
        $this->reconcile($session);
        $operator = $this->operator();

        [$link] = app(LinkManually::class)->handle($operator, $authorization, [$payment]);
        app(RemoveLink::class)->handle($operator, $link);

        $this->assertSame(
            [AuditAction::ManualLinkCreated, AuditAction::LinkRemoved],
            AuditLog::query()->where('auditable_type', 'reconciliation_link')->orderBy('id')->get()->map->action->all(),
        );
        $this->assertSame([$operator->id], AuditLog::query()->where('auditable_type', 'reconciliation_link')->pluck('user_id')->unique()->all());
    }
}
