<?php

namespace Tests\Feature\Reconciliation;

use App\Actions\Conciliation\ExecuteReconciliation;
use App\Contracts\ReconciliationEngine;
use App\Enums\AuditAction;
use App\Enums\AuthorizationStatus;
use App\Enums\DifferenceType;
use App\Enums\ImportSlot;
use App\Enums\LinkOrigin;
use App\Enums\MatchClassification;
use App\Enums\ReconciliationRunStatus;
use App\Enums\SessionStatus;
use App\Models\AuditLog;
use App\Models\ImportFile;
use App\Models\PendingItem;
use App\Models\ReconciliationLink;
use App\Models\ReconciliationRun;
use App\Models\ReconciliationSkip;
use App\Models\ReconciliationSuggestion;
use App\Services\Reconciliation\MatchResultWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class RunReconciliationTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    public function test_each_entry_falls_in_the_expected_classification(): void
    {
        $session = $this->sessionWithFiles(state: 'open');

        $exact = $this->authorization($session, 'PADARIA PERNAMBUCANA LTDA', 125040);
        $exactPayment = $this->payment($session, 'PADARIA PERNAMBUCANA', 125040);
        $cents = $this->authorization($session, 'PAPELARIA CENTRAL LTDA', 43000);
        $centsPayment = $this->payment($session, 'PAPELARIA CENTRAL', 43030);
        $doubtful = $this->authorization($session, 'MERCADO BOM PRECO CENTRO', 50000);
        $doubtfulPayment = $this->payment($session, 'MERCADO BOM PRXYO', 50000);
        $partial = $this->authorization($session, 'CONSTRUTORA HORIZONTE', 300000);
        $partialPayment = $this->payment($session, 'CONSTRUTORA HORIZONTE', 100000);
        $excess = $this->authorization($session, 'GRAFICA RAPIDA', 50000);
        $excessPayment = $this->payment($session, 'GRAFICA RAPIDA', 65000);
        $withoutPayment = $this->authorization($session, 'SERRALHERIA UNIAO', 77700);
        $orphan = $this->payment($session, 'POSTO DE COMBUSTIVEL ALFA', 9900);

        $run = $this->reconcile($session);

        $this->assertSame(ReconciliationRunStatus::Completed, $run->status);
        $this->assertSame(2, ReconciliationLink::query()->count());

        $link = $exactPayment->link;
        $this->assertTrue($link->authorization->is($exact));
        $this->assertSame(LinkOrigin::Automatic, $link->origin);
        $this->assertNull($link->decided_by);
        $this->assertSame(MatchClassification::Automatic, $link->engine_classification);
        $this->assertSame(100, $link->score);
        $this->assertSame(AuthorizationStatus::Reconciled, $exact->refresh()->status());

        $this->assertSame(DifferenceType::Exact, $centsPayment->link->difference_type);
        $this->assertSame(AuthorizationStatus::Reconciled, $cents->refresh()->status());

        $pending = PendingItem::query()->where('reconciliation_session_id', $session->id)->get()->keyBy('id');

        $this->assertSame('doubtful', $pending['s-'.$doubtfulPayment->suggestions()->sole()->id]->classification);
        $this->assertSame('partial', $pending['s-'.$partialPayment->suggestions()->sole()->id]->classification);
        $this->assertSame('excess', $pending['s-'.$excessPayment->suggestions()->sole()->id]->classification);
        $this->assertSame('unmatched_authorization', $pending['a-'.$withoutPayment->id]->classification);
        $this->assertSame('unmatched_payment', $pending['p-'.$orphan->id]->classification);
        $this->assertCount(5, $pending);
        $this->assertNull($doubtfulPayment->link);

        $this->assertSame([
            'authorizations' => 6,
            'reconciled_automatically' => 2,
            'automatic_percent' => 33,
            'doubtful' => 1,
            'partial' => 1,
            'excess' => 1,
            'unmatched_authorizations' => 1,
            'unmatched_payments' => 1,
            'awaiting_decision' => 3,
            'payments' => 6,
            'payments_compared' => 6,
        ], array_intersect_key($run->totals, array_flip([
            'authorizations', 'reconciled_automatically', 'automatic_percent', 'doubtful', 'partial', 'excess',
            'unmatched_authorizations', 'unmatched_payments', 'awaiting_decision', 'payments', 'payments_compared',
        ])));
    }

    public function test_run_records_its_parameters_and_is_audited(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $this->authorization($session, 'PADARIA PERNAMBUCANA', 10000);
        $this->payment($session, 'PADARIA PERNAMBUCANA', 10000);

        $run = $this->reconcile($session);

        $this->assertSame(50, $run->tolerance_cents);
        $this->assertNull($run->tolerance_basis_points);
        $this->assertSame(1000, $run->surcharge_cap_basis_points);
        $this->assertSame([90, 60, 90, 3], [$run->automatic_threshold, $run->suggestion_threshold, $run->supplier_threshold, $run->lookback_months]);
        $this->assertNotNull($run->finished_at);

        $log = AuditLog::query()->where('action', AuditAction::ReconciliationCompleted)->sole();

        $this->assertSame('reconciliation_run', $log->auditable_type);
        $this->assertSame($run->id, $log->auditable_id);
        $this->assertSame(50, $log->after['tolerance_cents']);
        $this->assertSame(1, $log->after['totals']['reconciled_automatically']);
        $this->assertSame(0, AuditLog::query()->where('auditable_type', 'reconciliation_link')->count());
    }

    public function test_execution_through_the_session_panel_processes_the_session(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $operator = $this->operator();
        $this->authorization($session, 'PADARIA PERNAMBUCANA', 10000);
        $payment = $this->payment($session, 'PADARIA PERNAMBUCANA', 10000);

        app(ExecuteReconciliation::class)->handle($operator, $session);

        $session->refresh();

        $this->assertSame(SessionStatus::Processed, $session->status);
        $this->assertSame(100, $session->progress);
        $this->assertTrue($session->currentRun->requester->is($operator));
        $this->assertNotNull($payment->link);
    }

    public function test_progress_is_reported_while_running(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $this->authorization($session, 'PADARIA PERNAMBUCANA', 10000);
        $this->payment($session, 'PADARIA PERNAMBUCANA', 10000);
        $steps = [];

        app(ReconciliationEngine::class)->run($session, function (int $percent) use (&$steps): void {
            $steps[] = $percent;
        });

        $this->assertGreaterThanOrEqual(4, count($steps));
        $this->assertSame($steps, array_values(array_unique($steps)));
        $sorted = $steps;
        sort($sorted);
        $this->assertSame($sorted, $steps);
        $this->assertLessThanOrEqual(100, max($steps));
    }

    public function test_a_failure_leaves_no_result_behind(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $operator = $this->operator();
        $this->authorization($session, 'PADARIA PERNAMBUCANA', 10000);
        $this->payment($session, 'PADARIA PERNAMBUCANA', 10000);
        $this->payment($session, 'POSTO ALFA', 5000);

        $this->app->bind(MatchResultWriter::class, fn () => new class extends MatchResultWriter
        {
            public function __construct() {}

            public function write(...$arguments): void
            {
                throw new RuntimeException('Falha simulada na gravação.');
            }
        });

        try {
            app(ExecuteReconciliation::class)->handle($operator, $session);
        } catch (RuntimeException) {
        }

        $session->refresh();

        $this->assertSame(SessionStatus::Open, $session->status);
        $this->assertSame('Falha simulada na gravação.', $session->last_failure);
        $this->assertSame(ReconciliationRunStatus::Failed, ReconciliationRun::query()->sole()->status);
        $this->assertSame(0, ReconciliationLink::query()->count());
        $this->assertSame(0, ReconciliationSuggestion::query()->count());
        $this->assertSame(0, ReconciliationSkip::query()->count());
        $this->assertNull($session->currentRun);
    }

    public function test_no_automatic_link_is_beyond_the_tolerance(): void
    {
        $session = $this->sessionWithFiles(state: 'open');

        foreach (range(1, 30) as $index) {
            $this->authorization($session, 'FORNECEDOR NUMERO '.$index, 10000 + $index * 100);
            $this->payment($session, 'FORNECEDOR NUMERO '.$index, 10000 + $index * 100 + ($index % 7) * 20);
        }

        $this->reconcile($session);

        $links = ReconciliationLink::query()->with(['authorization', 'payment'])->get();

        $this->assertGreaterThan(5, $links->count());

        foreach ($links as $link) {
            $this->assertLessThanOrEqual(50, abs($link->payment->amount_cents - $link->authorization->amount_cents));
        }

        $this->assertSame($links->count(), $links->pluck('payment_entry_id')->unique()->count());
    }

    public function test_authorization_and_payment_of_different_units_are_compared(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $this->authorization($session, 'PADARIA PERNAMBUCANA', 10000);
        $this->authorization($session, 'PAPELARIA CENTRAL', 20000);
        $social = $this->payment($session, 'PADARIA PERNAMBUCANA', 10000, slot: ImportSlot::PaymentsSocial);
        $saude = $this->payment($session, 'PAPELARIA CENTRAL', 20000, slot: ImportSlot::PaymentsSaude);

        $this->reconcile($session);

        $this->assertNotNull($social->link);
        $this->assertNotNull($saude->link);
    }

    public function test_entries_of_a_replaced_file_are_ignored(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $this->authorization($session, 'PADARIA PERNAMBUCANA', 10000);
        $old = $this->payment($session, 'PADARIA PERNAMBUCANA', 10000);
        $old->importFile->update(['status' => 'replaced', 'replaced_at' => now()]);
        ImportFile::factory()->forSlot(ImportSlot::PaymentsSaude)->create(['reconciliation_session_id' => $session->id]);
        $current = $this->payment($session->refresh(), 'PADARIA PERNAMBUCANA', 10000);

        $this->reconcile($session);

        $this->assertNull($old->link);
        $this->assertNotNull($current->link);
    }
}
