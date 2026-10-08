<?php

namespace Tests\Feature\Conciliation;

use App\Actions\Conciliation\ActionRefusedException;
use App\Actions\Conciliation\ExecuteReconciliation;
use App\Contracts\ReconciliationEngine;
use App\Enums\AuditAction;
use App\Enums\ImportAttemptStatus;
use App\Enums\ImportSlot;
use App\Enums\SessionStatus;
use App\Jobs\ProcessImportAttempt;
use App\Jobs\RunReconciliation;
use App\Livewire\Sessions\Show;
use App\Models\AuditLog;
use App\Models\ImportAttempt;
use App\Models\ReconciliationSession;
use App\Models\User;
use App\Services\Import\LayoutRegistry;
use App\Services\Import\SpreadsheetValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use RuntimeException;
use Tests\Concerns\SubmitsSpreadsheets;
use Tests\Fakes\FakeReconciliationEngine;
use Tests\TestCase;

class ExecuteReconciliationTest extends TestCase
{
    use RefreshDatabase;
    use SubmitsSpreadsheets;

    protected FakeReconciliationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new FakeReconciliationEngine;
        $this->app->instance(ReconciliationEngine::class, $this->engine);

        config(['conciliation.engine_enabled' => true]);
    }

    protected function readySession(): ReconciliationSession
    {
        return ReconciliationSession::factory()->withActiveFiles()->create();
    }

    public function test_executing_locks_the_session_audits_and_queues_the_job(): void
    {
        Queue::fake();
        $this->travelTo('2026-06-02 09:00:00');
        $session = $this->readySession();
        $user = User::factory()->create();

        app(ExecuteReconciliation::class)->handle($user, $session);

        $session->refresh();

        $this->assertSame(SessionStatus::Processing, $session->status);
        $this->assertSame('2026-06-02 09:00:00', $session->processing_started_at->toDateTimeString());
        $this->assertSame(0, $session->progress);

        Queue::assertPushed(RunReconciliation::class, fn (RunReconciliation $job): bool => $job->sessionId === $session->id);

        $log = AuditLog::query()->where('action', AuditAction::ReconciliationRequested)->sole();

        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(['status' => 'open'], $log->before);
        $this->assertSame('processing', $log->after['status']);
        $this->assertEqualsCanonicalizing($session->activeFiles()->pluck('id')->all(), array_values($log->after['import_file_ids']));
        $this->assertEqualsCanonicalizing(['authorizations', 'payments_social', 'payments_saude'], array_keys($log->after['import_file_ids']));
    }

    public function test_successful_run_marks_the_session_as_processed(): void
    {
        $this->travelTo('2026-06-02 09:00:00');
        $session = $this->readySession();

        app(ExecuteReconciliation::class)->handle($session->creator, $session);

        $session->refresh();

        $this->assertSame(SessionStatus::Processed, $session->status);
        $this->assertSame('2026-06-02 09:00:00', $session->first_processed_at->toDateTimeString());
        $this->assertSame('2026-06-02 09:00:00', $session->processed_at->toDateTimeString());
        $this->assertSame(100, $session->progress);
        $this->assertFalse($session->result_stale);
        $this->assertSame(['discard:'.$session->id, 'run:'.$session->id], $this->engine->calls);
    }

    public function test_progress_reported_by_the_engine_is_stored(): void
    {
        $session = ReconciliationSession::factory()->processing()->withActiveFiles()->create();
        $seen = [];
        $engine = new class($seen) extends FakeReconciliationEngine
        {
            /**
             * @param  list<int|null>  $seen
             */
            public function __construct(public array &$seen) {}

            public function run(ReconciliationSession $session, callable $reportProgress): void
            {
                $reportProgress(40);
                $this->seen[] = $session->fresh()->progress;
                $reportProgress(140);
                $this->seen[] = $session->fresh()->progress;
            }
        };

        (new RunReconciliation($session->id))->handle($engine);

        $this->assertSame([40, 100], $seen);
    }

    public function test_execution_is_refused_while_a_slot_is_missing(): void
    {
        $session = ReconciliationSession::factory()->create();
        $this->submit($session, ImportSlot::Authorizations, $this->authorizationsXlsx($this->authorizationRows(1)));
        Queue::fake();

        try {
            app(ExecuteReconciliation::class)->handle($session->creator, $session);
            $this->fail('The execution should have been refused.');
        } catch (ActionRefusedException $exception) {
            $this->assertStringNotContainsString(ImportSlot::Authorizations->label(), $exception->getMessage());
            $this->assertStringContainsString(ImportSlot::PaymentsSocial->label(), $exception->getMessage());
            $this->assertStringContainsString(ImportSlot::PaymentsSaude->label(), $exception->getMessage());
        }

        $this->assertSame(SessionStatus::Open, $session->fresh()->status);
        Queue::assertNotPushed(RunReconciliation::class);
        $this->assertSame(0, AuditLog::query()->where('action', AuditAction::ReconciliationRequested)->count());
    }

    public function test_second_execution_does_not_start_while_processing(): void
    {
        Queue::fake();
        $session = $this->readySession();

        app(ExecuteReconciliation::class)->handle($session->creator, $session);

        try {
            app(ExecuteReconciliation::class)->handle($session->creator, $session);
            $this->fail('The second execution should have been refused.');
        } catch (ActionRefusedException $exception) {
            $this->assertSame(__('conciliation.sessions.errors.locked', ['status' => SessionStatus::Processing->label()]), $exception->getMessage());
        }

        Queue::assertPushed(RunReconciliation::class, 1);
    }

    public function test_processed_session_cannot_be_executed_again_without_reopening(): void
    {
        Queue::fake();
        $session = ReconciliationSession::factory()->processed()->withActiveFiles()->create();

        $this->expectException(ActionRefusedException::class);

        app(ExecuteReconciliation::class)->handle($session->creator, $session);
    }

    public function test_failed_run_returns_the_session_to_open_without_partial_result(): void
    {
        $session = ReconciliationSession::factory()->processing()->withActiveFiles()->create();
        $this->engine->shouldFail = true;
        $job = new RunReconciliation($session->id);

        try {
            $job->handle($this->engine);
            $this->fail('The engine should have failed.');
        } catch (RuntimeException $exception) {
            $job->failed($exception);
        }

        $session->refresh();

        $this->assertSame(SessionStatus::Open, $session->status);
        $this->assertNull($session->first_processed_at);
        $this->assertNull($session->progress);
        $this->assertSame('Falha simulada do motor.', $session->last_failure);
        $this->assertSame(3, $session->activeFiles()->count());
        $this->assertSame('discard:'.$session->id, end($this->engine->calls));

        Livewire::actingAs($session->creator)
            ->test(Show::class, ['session' => $session])
            ->assertSee(__('conciliation.sessions.execute.failed', ['message' => 'Falha simulada do motor.']));
    }

    public function test_job_does_nothing_when_the_session_is_not_processing(): void
    {
        $session = $this->readySession();

        (new RunReconciliation($session->id))->handle($this->engine);

        $this->assertSame([], $this->engine->calls);
        $this->assertSame(SessionStatus::Open, $session->fresh()->status);
    }

    public function test_job_is_unique_per_session(): void
    {
        $this->assertSame('15', (new RunReconciliation(15))->uniqueId());
    }

    public function test_execution_is_refused_while_the_engine_is_disabled(): void
    {
        Queue::fake();
        config(['conciliation.engine_enabled' => false]);
        $session = $this->readySession();

        try {
            app(ExecuteReconciliation::class)->handle($session->creator, $session);
            $this->fail('The execution should have been refused.');
        } catch (ActionRefusedException $exception) {
            $this->assertSame(__('conciliation.sessions.execute.engine_disabled'), $exception->getMessage());
        }

        Queue::assertNothingPushed();

        Livewire::actingAs($session->creator)
            ->test(Show::class, ['session' => $session])
            ->assertSee(__('conciliation.sessions.execute.engine_disabled'));
    }

    public function test_panel_executes_and_shows_the_locked_session(): void
    {
        Queue::fake();
        $session = $this->readySession();

        Livewire::actingAs($session->creator)
            ->test(Show::class, ['session' => $session])
            ->call('execute')
            ->assertSee(__('conciliation.sessions.status.processing'))
            ->assertSee(__('conciliation.sessions.execute.progress', ['percent' => 0]))
            ->assertSee(__('conciliation.sessions.execute.locked_notice'))
            ->assertSeeHtml('wire:poll')
            ->assertDontSee(__('conciliation.slots.choose_file'))
            ->assertDontSee(__('conciliation.slots.replace'))
            ->assertDontSee(__('conciliation.sessions.delete.action'));
    }

    public function test_execution_requested_in_another_tab_is_refused_here(): void
    {
        Queue::fake();
        $session = $this->readySession();
        $otherTab = Livewire::actingAs($session->creator)->test(Show::class, ['session' => $session]);

        Livewire::actingAs($session->creator)->test(Show::class, ['session' => $session])->call('execute');

        $otherTab->call('execute');

        Queue::assertPushed(RunReconciliation::class, 1);
    }

    public function test_upload_is_refused_once_the_session_is_locked_in_another_tab(): void
    {
        Queue::fake();
        $session = $this->readySession();
        $otherTab = Livewire::actingAs($session->creator)->test(Show::class, ['session' => $session]);

        app(ExecuteReconciliation::class)->handle($session->creator, $session);

        $otherTab
            ->set('uploads.payments_social', $this->paymentsCsv($this->paymentRows(1)))
            ->assertSee(__('conciliation.sessions.errors.locked', ['status' => SessionStatus::Processing->label()]));

        $this->assertSame(0, ImportAttempt::query()->count());
    }

    public function test_upload_validated_before_the_lock_is_not_persisted_after_it(): void
    {
        Queue::fake();
        $session = $this->readySession();
        $attempt = $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(1)));
        $session->update(['status' => SessionStatus::Processing, 'processing_started_at' => now()]);

        (new ProcessImportAttempt($attempt->id))->handle(app(SpreadsheetValidator::class), app(LayoutRegistry::class));

        $attempt->refresh();

        $this->assertSame(ImportAttemptStatus::Rejected, $attempt->status);
        $this->assertSame(__('conciliation.sessions.errors.locked', ['status' => SessionStatus::Processing->label()]), $attempt->message);
        $this->assertSame(3, $session->importFiles()->count());
    }

    public function test_recover_command_reopens_only_sessions_stuck_in_processing(): void
    {
        $stuck = ReconciliationSession::factory()->processing()->create(['processing_started_at' => now()->subMinutes(121)]);
        $running = ReconciliationSession::factory()->processing()->create(['processing_started_at' => now()->subMinutes(119)]);
        $processed = ReconciliationSession::factory()->processed()->create(['processing_started_at' => now()->subDays(2)]);

        $this->artisan('conciliation:recover-stuck-sessions')->assertSuccessful();

        $stuck->refresh();

        $this->assertSame(SessionStatus::Open, $stuck->status);
        $this->assertSame(__('conciliation.sessions.execute.stalled'), $stuck->last_failure);
        $this->assertNull($stuck->progress);
        $this->assertSame(['discard:'.$stuck->id], $this->engine->calls);
        $this->assertSame(SessionStatus::Processing, $running->fresh()->status);
        $this->assertSame(SessionStatus::Processed, $processed->fresh()->status);
    }
}
