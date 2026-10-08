<?php

namespace Tests\Feature\Conciliation;

use App\Actions\Conciliation\ActionRefusedException;
use App\Actions\Conciliation\ExecuteReconciliation;
use App\Actions\Conciliation\ReopenSession;
use App\Contracts\ReconciliationEngine;
use App\Contracts\ReconciliationResultInspector;
use App\Enums\AuditAction;
use App\Enums\ImportFileStatus;
use App\Enums\ImportSlot;
use App\Enums\SessionStatus;
use App\Livewire\Sessions\Show;
use App\Models\AuditLog;
use App\Models\PaymentEntry;
use App\Models\ReconciliationSession;
use App\Models\User;
use App\Services\Import\NullReconciliationResultInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SubmitsSpreadsheets;
use Tests\Fakes\FakeReconciliationEngine;
use Tests\TestCase;

class ReopenSessionTest extends TestCase
{
    use RefreshDatabase;
    use SubmitsSpreadsheets;

    protected function blockReopeningWith(int $decisions): void
    {
        $this->app->instance(ReconciliationResultInspector::class, new class($decisions) implements ReconciliationResultInspector
        {
            public function __construct(protected int $decisions) {}

            public function blockingDecisionCount(ReconciliationSession $session): int
            {
                return $this->decisions;
            }
        });
    }

    public function test_default_inspector_reports_no_blocking_decision(): void
    {
        $this->assertInstanceOf(NullReconciliationResultInspector::class, app(ReconciliationResultInspector::class));
        $this->assertSame(0, app(ReconciliationResultInspector::class)->blockingDecisionCount(ReconciliationSession::factory()->create()));
    }

    public function test_reopening_a_processed_session_unlocks_it_and_is_audited(): void
    {
        $session = ReconciliationSession::factory()->processed()->withActiveFiles()->create();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Show::class, ['session' => $session])
            ->assertSee(__('conciliation.sessions.reopen.action'))
            ->call('reopen')
            ->assertSee(__('conciliation.sessions.status.open'))
            ->assertSee(__('conciliation.sessions.reopen.stale'))
            ->assertSee(__('conciliation.slots.replace'));

        $session->refresh();

        $this->assertSame(SessionStatus::Open, $session->status);
        $this->assertTrue($session->result_stale);
        $this->assertTrue($session->hasEverBeenProcessed());

        $log = AuditLog::query()->where('action', AuditAction::SessionReopened)->sole();

        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($session->id, $log->auditable_id);
        $this->assertSame(['status' => 'processed'], $log->before);
        $this->assertSame(['status' => 'open'], $log->after);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function statesThatCannotBeReopened(): array
    {
        return ['open' => ['open'], 'processing' => ['processing']];
    }

    #[DataProvider('statesThatCannotBeReopened')]
    public function test_only_processed_sessions_can_be_reopened(string $state): void
    {
        $session = $state === 'processing'
            ? ReconciliationSession::factory()->processing()->create()
            : ReconciliationSession::factory()->create();

        try {
            app(ReopenSession::class)->handle($session->creator, $session);
            $this->fail('The reopening should have been refused.');
        } catch (ActionRefusedException $exception) {
            $this->assertSame(__('conciliation.sessions.reopen.not_processed'), $exception->getMessage());
        }

        $this->assertSame($state, $session->fresh()->status->value);
        $this->assertSame(0, AuditLog::query()->where('action', AuditAction::SessionReopened)->count());
    }

    public function test_reopening_is_blocked_while_manual_decisions_exist(): void
    {
        $this->blockReopeningWith(3);
        $session = ReconciliationSession::factory()->processed()->withActiveFiles()->create();

        try {
            app(ReopenSession::class)->handle($session->creator, $session);
            $this->fail('The reopening should have been refused.');
        } catch (ActionRefusedException $exception) {
            $this->assertSame(trans_choice('conciliation.sessions.reopen.blocked', 3, ['count' => 3]), $exception->getMessage());
            $this->assertStringContainsString('3', $exception->getMessage());
        }

        $session->refresh();

        $this->assertSame(SessionStatus::Processed, $session->status);
        $this->assertFalse($session->result_stale);
        $this->assertSame(0, AuditLog::query()->where('action', AuditAction::SessionReopened)->count());
    }

    public function test_reopening_happens_once_the_manual_decisions_are_undone(): void
    {
        $session = ReconciliationSession::factory()->processed()->withActiveFiles()->create();

        $this->blockReopeningWith(1);

        try {
            app(ReopenSession::class)->handle($session->creator, $session);
        } catch (ActionRefusedException) {
            $this->assertSame(SessionStatus::Processed, $session->fresh()->status);
        }

        $this->blockReopeningWith(0);
        app(ReopenSession::class)->handle($session->creator, $session);

        $this->assertSame(SessionStatus::Open, $session->fresh()->status);
    }

    public function test_replacing_a_file_in_a_reopened_session_keeps_the_previous_one_as_evidence(): void
    {
        $session = ReconciliationSession::factory()->create();
        $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(2), 'original.csv'));
        $previous = $session->activeFiles()->sole();
        $session->update(['status' => SessionStatus::Processed, 'first_processed_at' => now(), 'processed_at' => now()]);

        app(ReopenSession::class)->handle($session->creator, $session);
        $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(3), 'corrigido.csv'));

        $previous->refresh();

        $this->assertSame(ImportFileStatus::Replaced, $previous->status);
        $this->assertSame(2, PaymentEntry::query()->where('import_file_id', $previous->id)->count());
        Storage::disk('local')->assertExists($previous->path);
        $this->assertSame('corrigido.csv', $session->activeFiles()->sole()->original_name);
        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::FileReplaced)->count());

        $this->actingAs($session->creator)
            ->get(route('sessions.files.download', [$session, $previous]))
            ->assertOk()
            ->assertDownload('original.csv');
    }

    public function test_invalid_replacement_in_a_reopened_session_is_refused(): void
    {
        $session = ReconciliationSession::factory()->reopened()->create();
        $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(2), 'original.csv'));

        $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(2, ['CEDENTE' => '']), 'invalido.csv'));

        $this->assertSame('original.csv', $session->activeFiles()->sole()->original_name);
    }

    public function test_running_again_discards_the_previous_result_first_and_clears_the_stale_mark(): void
    {
        $engine = new FakeReconciliationEngine;
        $this->app->instance(ReconciliationEngine::class, $engine);
        config(['conciliation.engine_enabled' => true]);

        $session = ReconciliationSession::factory()->reopened()->withActiveFiles()->create();
        $firstProcessedAt = $session->first_processed_at->toDateTimeString();

        $this->travel(1)->days();
        app(ExecuteReconciliation::class)->handle($session->creator, $session);

        $session->refresh();

        $this->assertSame(['discard:'.$session->id, 'run:'.$session->id], $engine->calls);
        $this->assertSame(SessionStatus::Processed, $session->status);
        $this->assertFalse($session->result_stale);
        $this->assertSame($firstProcessedAt, $session->first_processed_at->toDateTimeString());
        $this->assertTrue($session->processed_at->greaterThan($session->first_processed_at));
    }

    public function test_panel_shows_the_refusal_with_the_number_of_decisions(): void
    {
        $this->blockReopeningWith(1);
        $session = ReconciliationSession::factory()->processed()->withActiveFiles()->create();

        Livewire::actingAs($session->creator)
            ->test(Show::class, ['session' => $session])
            ->call('reopen')
            ->assertDispatched('banner-message', style: 'danger', message: trans_choice('conciliation.sessions.reopen.blocked', 1, ['count' => 1]))
            ->assertSee(__('conciliation.sessions.status.processed'));
    }
}
