<?php

namespace Tests\Feature\Conciliation;

use App\Actions\Conciliation\DeleteSession;
use App\Actions\Conciliation\ReopenSession;
use App\Enums\AuditAction;
use App\Enums\ImportSlot;
use App\Livewire\Sessions\History;
use App\Models\AuditLog;
use App\Models\ReconciliationSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SubmitsSpreadsheets;
use Tests\TestCase;

class SessionHistoryTest extends TestCase
{
    use RefreshDatabase;
    use SubmitsSpreadsheets;

    public function test_administrator_sees_deletions_and_reopenings(): void
    {
        $this->travelTo('2026-06-10 17:20:30');
        $operator = User::factory()->create(['name' => 'Paula Operadora']);
        $deleted = ReconciliationSession::factory()->create(['period' => '2026-05-01']);
        $this->submit($deleted, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(1), 'social-maio.csv'));
        $deletedLabel = $deleted->label();
        $reopened = ReconciliationSession::factory()->processed()->create(['period' => '2026-04-01']);

        app(DeleteSession::class)->handle($operator, $deleted);
        app(ReopenSession::class)->handle($operator, $reopened);

        $deletion = AuditLog::query()->where('action', AuditAction::SessionDeleted)->sole();
        $reopening = AuditLog::query()->where('action', AuditAction::SessionReopened)->sole();
        $creation = AuditLog::factory()->create(['action' => AuditAction::SessionCreated]);

        Livewire::actingAs(User::factory()->administrador()->create())
            ->test(History::class)
            ->assertCanSeeTableRecords([$deletion, $reopening])
            ->assertCanNotSeeTableRecords([$creation])
            ->assertTableColumnStateSet('user.name', 'Paula Operadora', $deletion)
            ->assertTableColumnStateSet('label', $deletedLabel, $deletion)
            ->assertTableColumnStateSet('files', ['social-maio.csv'], $deletion)
            ->assertTableColumnFormattedStateSet('action', AuditAction::SessionDeleted->label(), $deletion)
            ->assertTableColumnFormattedStateSet('created_at', '10/06/2026 14:20:30', $deletion)
            ->assertTableColumnStateSet('label', $reopened->label(), $reopening);
    }

    public function test_history_can_be_filtered_by_action(): void
    {
        $deletion = AuditLog::factory()->create(['action' => AuditAction::SessionDeleted, 'before' => ['files' => []]]);
        $reopening = AuditLog::factory()->create(['action' => AuditAction::SessionReopened]);

        Livewire::actingAs(User::factory()->administrador()->create())
            ->test(History::class)
            ->filterTable('action', AuditAction::SessionReopened->value)
            ->assertCanSeeTableRecords([$reopening])
            ->assertCanNotSeeTableRecords([$deletion]);
    }

    public function test_history_page_is_displayed_to_administrators(): void
    {
        $this->actingAs(User::factory()->administrador()->create())
            ->get(route('sessions.history'))
            ->assertOk()
            ->assertSeeLivewire(History::class)
            ->assertSee(__('conciliation.sessions.history.nav'));
    }

    public function test_operator_is_denied(): void
    {
        $operator = User::factory()->create();

        $this->actingAs($operator)->get(route('sessions.history'))->assertForbidden();

        Livewire::actingAs($operator)->test(History::class)->assertForbidden();

        $this->actingAs($operator)
            ->get(route('sessions.index'))
            ->assertDontSee(__('conciliation.sessions.history.nav'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('sessions.history'))->assertRedirect(route('login'));
    }
}
