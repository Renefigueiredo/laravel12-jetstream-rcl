<?php

namespace Tests\Feature\Conciliation;

use App\Enums\ImportAttemptStatus;
use App\Enums\ImportSlot;
use App\Livewire\Sessions\Show;
use App\Models\ImportAttempt;
use App\Models\ImportFile;
use App\Models\PaymentEntry;
use App\Models\ReconciliationSession;
use App\Models\User;
use App\Services\Import\Layouts\PaymentsLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SubmitsSpreadsheets;
use Tests\TestCase;

class SessionPanelTest extends TestCase
{
    use RefreshDatabase;
    use SubmitsSpreadsheets;

    public function test_new_session_shows_three_pending_slots_and_execution_unavailable(): void
    {
        $session = ReconciliationSession::factory()->create();

        $this->actingAs($session->creator)
            ->get(route('sessions.show', $session))
            ->assertOk()
            ->assertSeeLivewire(Show::class)
            ->assertSee($session->label())
            ->assertSee(ImportSlot::Authorizations->label())
            ->assertSee(ImportSlot::PaymentsSocial->label())
            ->assertSee(ImportSlot::PaymentsSaude->label())
            ->assertSee(__('conciliation.slots.status.pending'))
            ->assertDontSee(__('conciliation.slots.status.loaded'))
            ->assertSee(__('conciliation.sessions.execute.missing', [
                'slots' => implode(', ', array_map(fn (ImportSlot $slot): string => $slot->label(), ImportSlot::cases())),
            ]));
    }

    public function test_uploading_through_the_panel_loads_the_slot(): void
    {
        $session = ReconciliationSession::factory()->create();
        $user = User::factory()->create(['name' => 'Carlos Operador']);
        $rows = [...$this->paymentRows(4), $this->paymentRow(['VL_RECEBIDO' => '0'])];

        Livewire::actingAs($user)
            ->test(Show::class, ['session' => $session])
            ->set('uploads.payments_social', $this->paymentsCsv($rows, 'social-maio.csv'))
            ->assertHasNoErrors()
            ->assertSee('social-maio.csv')
            ->assertSee(trans_choice('conciliation.slots.entries', 4, ['count' => 4]))
            ->assertSee(trans_choice('conciliation.slots.skipped_value', 1, ['count' => 1]))
            ->assertSee('Carlos Operador')
            ->assertSee(__('conciliation.slots.status.loaded'))
            ->assertSee(__('conciliation.slots.download_original'));

        $this->assertSame(4, PaymentEntry::query()->count());
    }

    public function test_panel_points_out_the_missing_slot(): void
    {
        $session = ReconciliationSession::factory()->create();
        $this->submit($session, ImportSlot::Authorizations, $this->authorizationsXlsx($this->authorizationRows(1)));
        $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(1)));

        Livewire::actingAs($session->creator)
            ->test(Show::class, ['session' => $session])
            ->assertSee(__('conciliation.sessions.execute.missing', ['slots' => ImportSlot::PaymentsSaude->label()]));
    }

    public function test_panel_reports_skipped_existing_first_sheet_and_missing_columns(): void
    {
        $session = ReconciliationSession::factory()->create();
        ImportFile::factory()->forSlot(ImportSlot::PaymentsSocial)->create([
            'reconciliation_session_id' => $session->id,
            'rows_imported' => 15,
            'rows_skipped_existing' => 200,
            'sheet_count' => 3,
            'missing_columns' => ['DS_AUDIT'],
        ]);

        Livewire::actingAs($session->creator)
            ->test(Show::class, ['session' => $session])
            ->assertSee(trans_choice('conciliation.slots.skipped_existing', 200, ['count' => 200]))
            ->assertSee(__('conciliation.slots.first_sheet_only', ['count' => 3]))
            ->assertSee(__('conciliation.slots.missing_columns', ['columns' => 'DS_AUDIT']));
    }

    public function test_same_file_again_shows_already_loaded(): void
    {
        $session = ReconciliationSession::factory()->create();
        $rows = $this->paymentRows(2);
        $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($rows));

        Livewire::actingAs($session->creator)
            ->test(Show::class, ['session' => $session])
            ->set('uploads.payments_social', $this->paymentsCsv($rows))
            ->assertSee(__('conciliation.import.already_loaded'));

        $this->assertSame(1, ImportFile::query()->count());
    }

    public function test_refusal_is_shown_on_the_slot(): void
    {
        $session = ReconciliationSession::factory()->create();
        $rows = $this->paymentRows(2);
        $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($rows));

        Livewire::actingAs($session->creator)
            ->test(Show::class, ['session' => $session])
            ->set('uploads.payments_saude', $this->paymentsCsv($rows))
            ->assertSee(__('conciliation.errors.same_file_other_slot'));
    }

    public function test_upload_that_fails_to_reach_the_server_is_explained_in_portuguese(): void
    {
        $session = ReconciliationSession::factory()->create();

        Livewire::actingAs($session->creator)
            ->test(Show::class, ['session' => $session])
            ->call('_uploadErrored', 'uploads.authorizations', null, false)
            ->assertHasErrors('uploads.authorizations')
            ->assertSee(__('validation.uploaded', ['attribute' => 'Autorizações']))
            ->assertDontSee('failed to upload');
    }

    public function test_panel_polls_only_while_work_is_in_progress(): void
    {
        $session = ReconciliationSession::factory()->create();

        Livewire::actingAs($session->creator)
            ->test(Show::class, ['session' => $session])
            ->assertDontSeeHtml('wire:poll');

        ImportAttempt::factory()->withStatus(ImportAttemptStatus::Validating)->create([
            'reconciliation_session_id' => $session->id,
            'progress' => 10,
        ]);

        Livewire::actingAs($session->creator)
            ->test(Show::class, ['session' => $session])
            ->assertSeeHtml('wire:poll')
            ->assertSee(__('conciliation.import.in_progress', ['percent' => 10]));
    }

    public function test_deleted_session_leads_back_to_the_list(): void
    {
        $session = ReconciliationSession::factory()->create();
        $component = Livewire::actingAs($session->creator)->test(Show::class, ['session' => $session]);

        $session->delete();

        $component
            ->set('uploads.payments_social', $this->csvFile(app(PaymentsLayout::class)->headers(), $this->paymentRows(1)))
            ->assertRedirect(route('sessions.index'))
            ->assertSessionHas('flash.banner', __('conciliation.sessions.not_found'));
    }

    public function test_unknown_session_is_not_found(): void
    {
        $this->actingAs(User::factory()->create())->get(route('sessions.show', 999))->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $session = ReconciliationSession::factory()->create();

        $this->get(route('sessions.show', $session))->assertRedirect(route('login'));
    }
}
