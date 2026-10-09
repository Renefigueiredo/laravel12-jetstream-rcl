<?php

namespace Tests\Feature\Conciliation;

use App\Actions\Conciliation\ActionRefusedException;
use App\Actions\Conciliation\DeleteSession;
use App\Enums\AuditAction;
use App\Enums\ImportSlot;
use App\Livewire\Sessions\Index;
use App\Livewire\Sessions\Show;
use App\Models\AuditLog;
use App\Models\AuthorizationEntry;
use App\Models\ImportAttempt;
use App\Models\ImportFile;
use App\Models\PaymentEntry;
use App\Models\ReconciliationSession;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\SubmitsSpreadsheets;
use Tests\TestCase;

class DeleteSessionTest extends TestCase
{
    use RefreshDatabase;
    use SubmitsSpreadsheets;

    public function test_never_processed_session_is_deleted_with_files_and_entries(): void
    {
        $session = ReconciliationSession::factory()->create(['period' => '2026-05-01']);
        $this->submit($session, ImportSlot::Authorizations, $this->authorizationsXlsx($this->authorizationRows(2), 'autorizacoes.xlsx'));
        $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(2), 'primeiro.csv'));
        $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(3), 'segundo.csv'));
        $this->submit($session, ImportSlot::PaymentsSaude, $this->paymentsCsv($this->paymentRows(2, ['CEDENTE' => '']), 'recusado.csv'));
        $user = User::factory()->create();

        $this->assertNotSame([], Storage::disk('local')->allFiles('conciliation'));

        Livewire::actingAs($user)
            ->test(Show::class, ['session' => $session])
            ->call('delete')
            ->assertRedirect(route('sessions.index'))
            ->assertSessionHas('flash.banner', __('conciliation.sessions.delete.done'));

        $this->assertSame(0, ReconciliationSession::query()->count());
        $this->assertSame(0, ImportFile::query()->count());
        $this->assertSame(0, ImportAttempt::query()->count());
        $this->assertSame(0, PaymentEntry::query()->count());
        $this->assertSame(0, AuthorizationEntry::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('conciliation'));
    }

    public function test_deletion_audit_record_survives_and_identifies_the_session(): void
    {
        $this->travelTo('2026-06-10 14:20:30');
        $session = ReconciliationSession::factory()->create(['period' => '2026-05-01']);
        $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(2), 'primeiro.csv'));
        $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(3), 'segundo.csv'));
        $user = User::factory()->create();
        $label = $session->label();

        app(DeleteSession::class)->handle($user, $session);

        $log = AuditLog::query()->where('action', AuditAction::SessionDeleted)->sole();

        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('2026-06-10 14:20:30', $log->created_at->toDateTimeString());
        $this->assertSame($session->id, $log->auditable_id);
        $this->assertSame($label, $log->label);
        $this->assertSame($session->id, $log->before['number']);
        $this->assertSame('05/2026', $log->before['period']);
        $this->assertSame('open', $log->before['status']);
        $this->assertSame(['primeiro.csv', 'segundo.csv'], array_column($log->before['files'], 'file'));
        $this->assertNull($log->after);
    }

    public function test_processed_session_cannot_be_deleted(): void
    {
        $session = ReconciliationSession::factory()->processed()->withActiveFiles()->create();

        try {
            app(DeleteSession::class)->handle($session->creator, $session);
            $this->fail('The deletion should have been refused.');
        } catch (ActionRefusedException $exception) {
            $this->assertSame(__('conciliation.sessions.delete.processed'), $exception->getMessage());
        }

        $this->assertSame(1, ReconciliationSession::query()->count());
        $this->assertSame(3, ImportFile::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_reopened_session_cannot_be_deleted(): void
    {
        $session = ReconciliationSession::factory()->reopened()->create();

        $this->expectExceptionMessage(__('conciliation.sessions.delete.processed'));

        app(DeleteSession::class)->handle($session->creator, $session);
    }

    public function test_session_in_processing_cannot_be_deleted(): void
    {
        $session = ReconciliationSession::factory()->processing()->create();

        $this->expectExceptionMessage(__('conciliation.sessions.delete.processing'));

        app(DeleteSession::class)->handle($session->creator, $session);
    }

    public function test_panel_offers_deletion_only_for_never_processed_sessions(): void
    {
        $open = ReconciliationSession::factory()->create();
        $processed = ReconciliationSession::factory()->processed()->create();
        $reopened = ReconciliationSession::factory()->reopened()->create();
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Show::class, ['session' => $open])->assertSee(__('conciliation.sessions.delete.action'));
        Livewire::actingAs($user)->test(Show::class, ['session' => $processed])->assertDontSee(__('conciliation.sessions.delete.action'));
        Livewire::actingAs($user)->test(Show::class, ['session' => $reopened])->assertDontSee(__('conciliation.sessions.delete.action'));

        Livewire::actingAs($user)
            ->test(Show::class, ['session' => $processed])
            ->call('delete')
            ->assertNoRedirect()
            ->assertSet('showingRefusal', true)
            ->assertSee(__('conciliation.sessions.refused_heading'))
            ->assertSee(__('conciliation.sessions.delete.processed'));

        $this->assertNotNull($processed->fresh());
    }

    public function test_list_deletes_through_the_row_action_and_hides_it_for_processed_sessions(): void
    {
        $open = ReconciliationSession::factory()->create();
        $processed = ReconciliationSession::factory()->processed()->create();

        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->assertActionVisible(TestAction::make('delete')->table($open))
            ->assertActionHidden(TestAction::make('delete')->table($processed))
            ->callAction(TestAction::make('delete')->table($open))
            ->assertDispatched('banner-message', style: 'success', message: __('conciliation.sessions.delete.done'));

        $this->assertNull($open->fresh());
        $this->assertNotNull($processed->fresh());
    }

    public function test_entries_of_a_deleted_session_no_longer_count_in_the_period(): void
    {
        $first = ReconciliationSession::factory()->create(['period' => '2026-05-01']);
        $complementary = ReconciliationSession::factory()->create(['period' => '2026-05-01']);
        $rows = $this->paymentRows(3);
        $this->submit($first, ImportSlot::PaymentsSocial, $this->paymentsCsv($rows, 'primeiro.csv'));

        app(DeleteSession::class)->handle($first->creator, $first);
        $this->submit($complementary, ImportSlot::PaymentsSocial, $this->paymentsCsv($rows, 'complementar.csv'));

        $file = $complementary->activeFiles()->sole();

        $this->assertSame(3, $file->rows_imported);
        $this->assertSame(0, $file->rows_skipped_existing);
    }

    public function test_action_on_a_session_deleted_in_another_tab_leads_to_the_list(): void
    {
        $session = ReconciliationSession::factory()->create();
        $otherTab = Livewire::actingAs($session->creator)->test(Show::class, ['session' => $session]);

        app(DeleteSession::class)->handle($session->creator, $session);

        $otherTab
            ->call('delete')
            ->assertRedirect(route('sessions.index'))
            ->assertSessionHas('flash.banner', __('conciliation.sessions.not_found'));

        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::SessionDeleted)->count());
    }
}
