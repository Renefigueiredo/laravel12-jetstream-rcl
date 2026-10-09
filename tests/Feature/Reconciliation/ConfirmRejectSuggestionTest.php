<?php

namespace Tests\Feature\Reconciliation;

use App\Actions\Conciliation\ActionRefusedException;
use App\Actions\Conciliation\ConfirmSuggestion;
use App\Actions\Conciliation\RejectSuggestion;
use App\Actions\Conciliation\ReopenSession;
use App\Enums\AuditAction;
use App\Enums\AuthorizationStatus;
use App\Enums\LinkOrigin;
use App\Enums\MatchClassification;
use App\Enums\PairBlockReason;
use App\Enums\SuggestionStatus;
use App\Models\AuditLog;
use App\Models\PendingItem;
use App\Models\ReconciliationLink;
use App\Models\ReconciliationPairBlock;
use App\Models\ReconciliationSuggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class ConfirmRejectSuggestionTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    public function test_confirming_a_doubtful_pair_links_it_as_manual_and_is_audited(): void
    {
        $this->travelTo('2026-08-10 17:20:30');
        $session = $this->sessionWithFiles(state: 'open');
        $authorization = $this->authorization($session, 'MERCADO BOM PRECO CENTRO', 50000);
        $payment = $this->payment($session, 'MERCADO BOM PRXYO', 50000);
        $this->reconcile($session);
        $suggestion = ReconciliationSuggestion::query()->sole();
        $operator = $this->operator();

        $link = app(ConfirmSuggestion::class)->handle($operator, $suggestion);

        $this->assertSame(LinkOrigin::Manual, $link->origin);
        $this->assertTrue($link->decider->is($operator));
        $this->assertSame(MatchClassification::Doubtful, $link->engine_classification);
        $this->assertSame($suggestion->score, $link->score);
        $this->assertSame(SuggestionStatus::Confirmed, $suggestion->refresh()->status);
        $this->assertSame(AuthorizationStatus::Reconciled, $authorization->refresh()->status());
        $this->assertSame(0, PendingItem::query()->count());
        $this->assertTrue($payment->link->is($link));

        $log = AuditLog::query()->where('action', AuditAction::SuggestionConfirmed)->sole();

        $this->assertTrue($log->user->is($operator));
        $this->assertSame('reconciliation_link', $log->auditable_type);
        $this->assertSame($link->id, $log->auditable_id);
        $this->assertSame($suggestion->score, $log->before['score']);
        $this->assertSame('doubtful', $log->before['classification']);
        $this->assertSame(0, $log->after['balance_cents']);
        $this->assertSame('2026-08-10 17:20:30', $log->created_at->format('Y-m-d H:i:s'));
    }

    public function test_confirming_one_of_two_tied_authorizations_frees_the_other(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $chosen = $this->authorization($session, 'PADARIA PERNAMBUCANA', 29000, ['request' => 'COFFEE BREAK - 00961']);
        $other = $this->authorization($session, 'PADARIA PERNAMBUCANA', 29000, ['request' => 'LANCHE - 00929']);
        $this->payment($session, 'PADARIA PERNAMBUCANA', 29000);
        $this->reconcile($session);

        app(ConfirmSuggestion::class)->handle($this->operator(), $chosen->suggestions()->sole());

        $this->assertSame(SuggestionStatus::Superseded, $other->suggestions()->sole()->status);
        $this->assertSame(AuthorizationStatus::Reconciled, $chosen->refresh()->status());
        $this->assertSame(['a-'.$other->id], PendingItem::query()->pluck('id')->all());
        $this->assertSame('unmatched_authorization', PendingItem::query()->sole()->classification);
    }

    public function test_rejecting_blocks_the_pair_and_shows_the_next_candidate(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $authorization = $this->authorization($session, 'MERCADO BOM PRECO CENTRO', 50000);
        $best = $this->payment($session, 'MERCADO BOM PRXYO', 50000, ['paid_on' => '2026-07-21']);
        $next = $this->payment($session, 'MERCADO BON PREXO', 50000, ['paid_on' => '2026-07-22']);
        $this->reconcile($session);
        $operator = $this->operator();

        $first = $best->suggestions()->sole();
        $this->assertSame(['s-'.$first->id], PendingItem::query()->where('kind', 'suggestion')->pluck('id')->all());

        app(RejectSuggestion::class)->handle($operator, $first);

        $this->assertSame(SuggestionStatus::Rejected, $first->refresh()->status);
        $this->assertSame(0, ReconciliationLink::query()->count());
        $this->assertSame(['s-'.$next->suggestions()->sole()->id], PendingItem::query()->where('kind', 'suggestion')->pluck('id')->all());
        $this->assertSame('p-'.$best->id, PendingItem::query()->where('kind', 'unmatched_payment')->sole()->id);

        $block = ReconciliationPairBlock::query()->sole();

        $this->assertSame(PairBlockReason::Rejected, $block->reason);
        $this->assertSame($authorization->identity_key, $block->authorization_identity_key);
        $this->assertSame($best->identity_key, $block->payment_identity_key);

        $log = AuditLog::query()->where('action', AuditAction::SuggestionRejected)->sole();

        $this->assertTrue($log->user->is($operator));
        $this->assertSame('rejected', $log->after['status']);

        app(RejectSuggestion::class)->handle($operator, $next->suggestions()->sole());

        $this->assertEqualsCanonicalizing(
            ['unmatched_authorization', 'unmatched_payment', 'unmatched_payment'],
            PendingItem::query()->pluck('classification')->all(),
        );
    }

    public function test_a_rejected_pair_does_not_come_back_after_a_new_run(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $this->authorization($session, 'MERCADO BOM PRECO CENTRO', 50000);
        $this->payment($session, 'MERCADO BOM PRXYO', 50000);
        $this->reconcile($session);
        $operator = $this->operator();

        app(RejectSuggestion::class)->handle($operator, ReconciliationSuggestion::query()->sole());
        app(ReopenSession::class)->handle($operator, $session);
        $this->reconcile($session);

        $this->assertSame(0, ReconciliationSuggestion::query()->count());
        $this->assertSame(0, ReconciliationLink::query()->count());
        $this->assertSame(2, PendingItem::query()->count());
    }

    public function test_only_the_first_of_two_decisions_on_the_same_pair_counts(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $this->authorization($session, 'MERCADO BOM PRECO CENTRO', 50000);
        $this->payment($session, 'MERCADO BOM PRXYO', 50000);
        $this->reconcile($session);
        $suggestion = ReconciliationSuggestion::query()->sole();
        $stale = $suggestion->fresh();

        app(ConfirmSuggestion::class)->handle($this->operator(), $suggestion);

        foreach ([ConfirmSuggestion::class, RejectSuggestion::class] as $action) {
            try {
                app($action)->handle($this->operator(), $stale);
                $this->fail('The second decision should have been refused.');
            } catch (ActionRefusedException $exception) {
                $this->assertSame(__('conciliation.reconciliation.errors.suggestion_not_pending'), $exception->getMessage());
            }
        }

        $this->assertSame(1, ReconciliationLink::query()->count());
        $this->assertSame(0, ReconciliationPairBlock::query()->count());
    }

    public function test_decisions_need_a_processed_session(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $this->authorization($session, 'MERCADO BOM PRECO CENTRO', 50000);
        $this->payment($session, 'MERCADO BOM PRXYO', 50000);
        $this->reconcile($session);
        $suggestion = ReconciliationSuggestion::query()->sole();
        $session->update(['status' => 'processing']);

        $this->expectException(ActionRefusedException::class);

        app(ConfirmSuggestion::class)->handle($this->operator(), $suggestion);
    }

    public function test_operators_and_administrators_can_decide(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $this->authorization($session, 'MERCADO BOM PRECO CENTRO', 50000);
        $this->payment($session, 'MERCADO BOM PRXYO', 50000);
        $this->authorization($session, 'GRAFICA RAPIDA CENTRO', 41105);
        $this->payment($session, 'GRAFICA RAPIDAS SUL', 41105);
        $this->reconcile($session);
        [$first, $second] = ReconciliationSuggestion::query()->orderBy('id')->get()->all();

        app(ConfirmSuggestion::class)->handle(User::factory()->create(), $first);
        app(RejectSuggestion::class)->handle(User::factory()->administrador()->create(), $second);

        $this->assertSame([SuggestionStatus::Confirmed, SuggestionStatus::Rejected], [$first->refresh()->status, $second->refresh()->status]);
    }
}
