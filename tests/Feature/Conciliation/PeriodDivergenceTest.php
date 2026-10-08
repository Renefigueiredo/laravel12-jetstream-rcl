<?php

namespace Tests\Feature\Conciliation;

use App\Enums\AuditAction;
use App\Enums\ImportAttemptStatus;
use App\Enums\ImportSlot;
use App\Livewire\Sessions\Show;
use App\Models\AuditLog;
use App\Models\ImportAttempt;
use App\Models\ImportFile;
use App\Models\PaymentEntry;
use App\Models\ReconciliationSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\SubmitsSpreadsheets;
use Tests\TestCase;

class PeriodDivergenceTest extends TestCase
{
    use RefreshDatabase;
    use SubmitsSpreadsheets;

    /**
     * Three rows in May and two in June, for a session of May.
     *
     * @return list<array<string, mixed>>
     */
    protected function rowsWithJuneDates(): array
    {
        return [
            ...$this->paymentRows(3, ['DT_LIQUIDACAO' => '28-MAY-26']),
            $this->paymentRow(['DT_LIQUIDACAO' => '01-JUN-26']),
            $this->paymentRow(['DT_LIQUIDACAO' => '03-JUN-26']),
        ];
    }

    protected function pendingAttempt(ReconciliationSession $session, ?User $user = null): ImportAttempt
    {
        return $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->rowsWithJuneDates(), 'junho.csv'), $user);
    }

    public function test_file_with_dates_outside_the_period_waits_for_confirmation(): void
    {
        $session = ReconciliationSession::factory()->create(['period' => '2026-05-01']);

        $attempt = $this->pendingAttempt($session);

        $this->assertSame(ImportAttemptStatus::AwaitingConfirmation, $attempt->status);
        $this->assertSame(2, $attempt->rows_out_of_period);
        $this->assertSame('2026-05-28', $attempt->min_date->toDateString());
        $this->assertSame('2026-06-03', $attempt->max_date->toDateString());
        $this->assertSame(0, ImportFile::query()->count());
        $this->assertSame(0, PaymentEntry::query()->count());
        Storage::disk('local')->assertExists($attempt->path);
    }

    public function test_alert_shows_the_quantity_and_the_date_range(): void
    {
        $session = ReconciliationSession::factory()->create(['period' => '2026-05-01']);
        $operator = User::factory()->create();
        $this->pendingAttempt($session, $operator);

        Livewire::actingAs(User::factory()->create())
            ->test(Show::class, ['session' => $session])
            ->assertSee(__('conciliation.import.divergence.heading'))
            ->assertSee(trans_choice('conciliation.import.divergence.body', 2, [
                'count' => 2, 'period' => '05/2026', 'from' => '28/05/2026', 'to' => '03/06/2026',
            ]))
            ->assertSee(__('conciliation.import.divergence.confirm'))
            ->assertSee(__('conciliation.slots.status.pending'));
    }

    public function test_confirming_accepts_the_file_marks_the_divergence_and_is_audited(): void
    {
        $this->travelTo('2026-06-05 10:00:00');
        $session = ReconciliationSession::factory()->create(['period' => '2026-05-01']);
        $operator = User::factory()->create();
        $confirmer = User::factory()->create();
        $attempt = $this->pendingAttempt($session, $operator);

        Livewire::actingAs($confirmer)
            ->test(Show::class, ['session' => $session])
            ->call('confirmDivergence', $attempt->id)
            ->assertSee(__('conciliation.slots.status.loaded'))
            ->assertSee(trans_choice('conciliation.slots.period_divergence', 2, ['count' => 2, 'from' => '28/05/2026', 'to' => '03/06/2026']));

        $file = $session->activeFiles()->sole();

        $this->assertSame(ImportAttemptStatus::Accepted, $attempt->fresh()->status);
        $this->assertTrue($file->period_divergence);
        $this->assertSame(2, $file->rows_out_of_period);
        $this->assertSame($confirmer->id, $file->divergence_confirmed_by);
        $this->assertSame('2026-06-05 10:00:00', $file->divergence_confirmed_at->toDateTimeString());
        $this->assertSame($operator->id, $file->uploaded_by);
        $this->assertSame(5, PaymentEntry::query()->count());

        $log = AuditLog::query()->where('action', AuditAction::PeriodDivergenceConfirmed)->sole();

        $this->assertSame($confirmer->id, $log->user_id);
        $this->assertSame($session->id, $log->auditable_id);
        $this->assertSame('junho.csv', $log->after['file']);
        $this->assertSame(2, $log->after['rows_out_of_period']);
        $this->assertSame('2026-06-03', $log->after['max_date']);
    }

    public function test_cancelling_discards_the_file_and_keeps_the_slot(): void
    {
        $session = ReconciliationSession::factory()->create(['period' => '2026-05-01']);
        $operator = User::factory()->create();
        $attempt = $this->pendingAttempt($session, $operator);

        Livewire::actingAs(User::factory()->create())
            ->test(Show::class, ['session' => $session])
            ->call('cancelAttempt', $attempt->id)
            ->assertSee(__('conciliation.import.divergence.cancelled'))
            ->assertDontSee(__('conciliation.import.divergence.heading'));

        $this->assertSame(ImportAttemptStatus::Cancelled, $attempt->fresh()->status);
        Storage::disk('local')->assertMissing($attempt->path);
        $this->assertSame(0, ImportFile::query()->count());
        $this->assertSame(0, AuditLog::query()->where('action', AuditAction::PeriodDivergenceConfirmed)->count());
    }

    public function test_cancelling_keeps_the_previous_file_of_the_slot(): void
    {
        $session = ReconciliationSession::factory()->create(['period' => '2026-05-01']);
        $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(2), 'maio.csv'));
        $operator = User::factory()->create();
        $attempt = $this->pendingAttempt($session, $operator);

        Livewire::actingAs(User::factory()->create())->test(Show::class, ['session' => $session])->call('cancelAttempt', $attempt->id);

        $this->assertSame('maio.csv', $session->activeFiles()->sole()->original_name);
    }

    public function test_opening_the_panel_again_cancels_only_the_own_pending_upload(): void
    {
        $session = ReconciliationSession::factory()->create(['period' => '2026-05-01']);
        $owner = User::factory()->create();
        $otherOperator = User::factory()->create();
        $attempt = $this->pendingAttempt($session, $owner);

        Livewire::actingAs($otherOperator)->test(Show::class, ['session' => $session]);

        $this->assertSame(ImportAttemptStatus::AwaitingConfirmation, $attempt->fresh()->status);

        Livewire::actingAs($owner)
            ->test(Show::class, ['session' => $session])
            ->assertDontSee(__('conciliation.import.divergence.heading'));

        $this->assertSame(ImportAttemptStatus::Cancelled, $attempt->fresh()->status);
        Storage::disk('local')->assertMissing($attempt->path);
    }

    public function test_new_upload_to_the_slot_cancels_the_pending_one(): void
    {
        $session = ReconciliationSession::factory()->create(['period' => '2026-05-01']);
        $pending = $this->pendingAttempt($session);

        $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(2), 'maio.csv'));

        $this->assertSame(ImportAttemptStatus::Cancelled, $pending->fresh()->status);
        $this->assertSame('maio.csv', $session->activeFiles()->sole()->original_name);
    }

    public function test_only_the_settlement_date_is_compared_with_the_period(): void
    {
        $session = ReconciliationSession::factory()->create(['period' => '2026-05-01']);

        $attempt = $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(2, [
            'DT_LIQUIDACAO' => '15-MAY-26',
            'DT_EMISSAO' => '10-APR-26',
            'DT_VENCIMENTO' => '08-SEP-26',
        ])));

        $this->assertSame(ImportAttemptStatus::Accepted, $attempt->status);
        $this->assertFalse($session->activeFiles()->sole()->period_divergence);
    }

    public function test_authorization_date_is_compared_with_the_period(): void
    {
        $session = ReconciliationSession::factory()->create(['period' => '2026-05-01']);

        $attempt = $this->submit($session, ImportSlot::Authorizations, $this->authorizationsXlsx([
            $this->authorizationRow(['MAP_DATA_AUTORIZACAO' => '30/04/2026']),
            $this->authorizationRow(['MAP_DATA_AUTORIZACAO' => '02/05/2026']),
        ]));

        $this->assertSame(ImportAttemptStatus::AwaitingConfirmation, $attempt->status);
        $this->assertSame(1, $attempt->rows_out_of_period);
    }

    public function test_confirmation_is_refused_when_the_session_is_locked(): void
    {
        $session = ReconciliationSession::factory()->create(['period' => '2026-05-01']);
        $operator = User::factory()->create();
        $attempt = $this->pendingAttempt($session, $operator);
        $component = Livewire::actingAs(User::factory()->create())->test(Show::class, ['session' => $session]);

        $session->update(['status' => 'processing', 'processing_started_at' => now()]);

        $component
            ->call('confirmDivergence', $attempt->id)
            ->assertSee(__('conciliation.sessions.errors.locked', ['status' => __('conciliation.sessions.status.processing')]));

        $this->assertSame(ImportAttemptStatus::AwaitingConfirmation, $attempt->fresh()->status);
        $this->assertSame(0, ImportFile::query()->count());
    }

    public function test_confirming_twice_does_not_duplicate_the_file(): void
    {
        $session = ReconciliationSession::factory()->create(['period' => '2026-05-01']);
        $operator = User::factory()->create();
        $attempt = $this->pendingAttempt($session, $operator);

        Livewire::actingAs(User::factory()->create())
            ->test(Show::class, ['session' => $session])
            ->call('confirmDivergence', $attempt->id)
            ->call('confirmDivergence', $attempt->id)
            ->assertSee(__('conciliation.import.divergence.not_pending'));

        $this->assertSame(1, ImportFile::query()->count());
        $this->assertSame(5, PaymentEntry::query()->count());
        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::PeriodDivergenceConfirmed)->count());
    }

    public function test_prune_command_cancels_expired_confirmations(): void
    {
        $session = ReconciliationSession::factory()->create(['period' => '2026-05-01']);
        $expired = $this->pendingAttempt($session);

        $this->travel(29)->minutes();
        $this->artisan('conciliation:prune-import-attempts')->assertSuccessful();
        $this->assertSame(ImportAttemptStatus::AwaitingConfirmation, $expired->fresh()->status);

        $this->travel(2)->minutes();
        $this->artisan('conciliation:prune-import-attempts')->assertSuccessful();

        $this->assertSame(ImportAttemptStatus::Cancelled, $expired->fresh()->status);
        Storage::disk('local')->assertMissing($expired->path);
    }

    public function test_prune_command_fails_stalled_uploads(): void
    {
        Storage::disk('local')->put('conciliation/incoming/parado.csv', 'x');
        $stalled = ImportAttempt::factory()->withStatus(ImportAttemptStatus::Validating)->create(['path' => 'conciliation/incoming/parado.csv']);
        $recent = ImportAttempt::factory()->withStatus(ImportAttemptStatus::Queued)->create();

        $this->travel(31)->minutes();
        $recent->touch();
        $this->artisan('conciliation:prune-import-attempts')->assertSuccessful();

        $this->assertSame(ImportAttemptStatus::Failed, $stalled->fresh()->status);
        $this->assertSame(__('conciliation.import.stalled'), $stalled->fresh()->message);
        $this->assertSame(ImportAttemptStatus::Queued, $recent->fresh()->status);
        Storage::disk('local')->assertMissing('conciliation/incoming/parado.csv');
    }

    public function test_prune_command_removes_old_finished_attempts_and_their_reports(): void
    {
        $session = ReconciliationSession::factory()->create(['period' => '2026-05-01']);
        $rejected = $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(2, ['CEDENTE' => ''])));
        $accepted = $this->submit($session, ImportSlot::PaymentsSaude, $this->paymentsCsv($this->paymentRows(2)));
        Storage::disk('local')->assertExists($rejected->error_report_path);

        $this->travel(23)->hours();
        $this->artisan('conciliation:prune-import-attempts')->assertSuccessful();
        $this->assertSame(2, ImportAttempt::query()->count());

        $this->travel(2)->hours();
        $this->artisan('conciliation:prune-import-attempts')->assertSuccessful();

        $this->assertSame(0, ImportAttempt::query()->count());
        Storage::disk('local')->assertMissing($rejected->error_report_path);
        Storage::disk('local')->assertExists($session->activeFiles()->sole()->path);
        $this->assertSame(1, ImportFile::query()->count());
        $this->assertNotNull($accepted);
    }
}
