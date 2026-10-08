<?php

namespace Tests\Feature\Conciliation;

use App\Enums\AuditAction;
use App\Enums\ImportSlot;
use App\Enums\SessionStatus;
use App\Livewire\Sessions\Index;
use App\Models\AuditLog;
use App\Models\ImportFile;
use App\Models\ReconciliationSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreateSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-06-20 15:00:00');
    }

    public function test_sessions_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('sessions.index'))
            ->assertOk()
            ->assertSeeLivewire(Index::class)
            ->assertSee(__('conciliation.sessions.new'));
    }

    public function test_operator_creates_an_open_session(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(Index::class)
            ->call('openCreateModal')
            ->set('period', '05/2026')
            ->call('createSession')
            ->assertHasNoErrors();

        $session = ReconciliationSession::query()->sole();

        $component->assertRedirect(route('sessions.show', $session));

        $this->assertSame('2026-05-01', $session->period->toDateString());
        $this->assertSame(SessionStatus::Open, $session->status);
        $this->assertSame($user->id, $session->created_by);
        $this->assertSame('2026-06-20 15:00:00', $session->created_at->toDateTimeString());
        $this->assertFalse($session->hasEverBeenProcessed());
    }

    public function test_session_creation_is_audited(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Index::class)->set('period', '05/2026')->call('createSession');

        $session = ReconciliationSession::query()->sole();
        $log = AuditLog::query()->sole();

        $this->assertSame(AuditAction::SessionCreated, $log->action);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($session->id, $log->auditable_id);
        $this->assertSame($session->label(), $log->label);
        $this->assertNull($log->before);
        $this->assertSame(['period' => '05/2026', 'status' => 'open'], $log->after);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidPeriods(): array
    {
        return [
            'month zero' => ['00/2026', 'conciliation.sessions.errors.period_format'],
            'month thirteen' => ['13/2026', 'conciliation.sessions.errors.period_format'],
            'empty' => ['', 'conciliation.sessions.errors.period_format'],
            'text' => ['maio/2026', 'conciliation.sessions.errors.period_format'],
            'two digit year' => ['05/26', 'conciliation.sessions.errors.period_format'],
            'iso' => ['2026-05', 'conciliation.sessions.errors.period_format'],
            'next month' => ['07/2026', 'conciliation.sessions.errors.period_future'],
            'next year' => ['01/2027', 'conciliation.sessions.errors.period_future'],
        ];
    }

    #[DataProvider('invalidPeriods')]
    public function test_invalid_or_future_period_is_refused(string $period, string $messageKey): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->set('period', $period)
            ->call('createSession')
            ->assertHasErrors('period')
            ->assertSee(__($messageKey));

        $this->assertSame(0, ReconciliationSession::query()->count());
    }

    public function test_current_month_is_accepted(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->set('period', '06/2026')
            ->call('createSession')
            ->assertHasNoErrors();

        $this->assertSame(1, ReconciliationSession::query()->count());
    }

    public function test_current_month_follows_the_display_timezone(): void
    {
        $this->travelTo('2026-07-01 01:00:00');

        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->set('period', '07/2026')
            ->call('createSession')
            ->assertHasErrors('period');

        $this->travelTo('2026-07-01 03:30:00');

        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->set('period', '07/2026')
            ->call('createSession')
            ->assertHasNoErrors();
    }

    public function test_period_with_a_session_requires_confirmation_of_a_complementary_session(): void
    {
        ReconciliationSession::factory()->create(['period' => '2026-05-01']);

        $component = Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->set('period', '05/2026')
            ->call('createSession')
            ->assertNoRedirect()
            ->assertSee(__('conciliation.sessions.complementary.heading'))
            ->assertSee(__('conciliation.sessions.complementary.confirm'));

        $this->assertSame(1, ReconciliationSession::query()->count());

        $component->call('createSession');

        $this->assertSame(2, ReconciliationSession::query()->whereDate('period', '2026-05-01')->count());
    }

    public function test_changing_the_period_discards_the_complementary_confirmation(): void
    {
        ReconciliationSession::factory()->create(['period' => '2026-05-01']);
        ReconciliationSession::factory()->create(['period' => '2026-04-01']);

        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->set('period', '05/2026')
            ->call('createSession')
            ->set('period', '04/2026')
            ->call('createSession')
            ->assertNoRedirect();

        $this->assertSame(2, ReconciliationSession::query()->count());
    }

    public function test_list_shows_the_sessions_with_the_status_of_the_three_slots(): void
    {
        $creator = User::factory()->create(['name' => 'Maria Operadora']);
        $session = ReconciliationSession::factory()->create(['period' => '2026-05-01', 'created_by' => $creator->id]);
        ImportFile::factory()->forSlot(ImportSlot::Authorizations)->create(['reconciliation_session_id' => $session->id]);

        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->assertCanSeeTableRecords([$session])
            ->assertTableColumnFormattedStateSet('period', '05/2026', $session)
            ->assertTableColumnFormattedStateSet('status', __('conciliation.sessions.status.open'), $session)
            ->assertTableColumnStateSet('creator.name', 'Maria Operadora', $session)
            ->assertTableColumnFormattedStateSet('slot_authorizations', __('conciliation.slots.status.loaded'), $session)
            ->assertTableColumnFormattedStateSet('slot_payments_social', __('conciliation.slots.status.pending'), $session)
            ->assertTableColumnFormattedStateSet('slot_payments_saude', __('conciliation.slots.status.pending'), $session);
    }

    public function test_list_can_be_searched_by_period_and_filtered_by_status(): void
    {
        $may = ReconciliationSession::factory()->create(['period' => '2026-05-01']);
        $aprilProcessed = ReconciliationSession::factory()->processed()->create(['period' => '2026-04-01']);

        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->searchTable('05/2026')
            ->assertCanSeeTableRecords([$may])
            ->assertCanNotSeeTableRecords([$aprilProcessed])
            ->searchTable('')
            ->filterTable('status', SessionStatus::Processed->value)
            ->assertCanSeeTableRecords([$aprilProcessed])
            ->assertCanNotSeeTableRecords([$may]);
    }

    public function test_search_and_filter_are_read_from_the_url(): void
    {
        $may = ReconciliationSession::factory()->create(['period' => '2026-05-01']);
        $april = ReconciliationSession::factory()->create(['period' => '2026-04-01']);
        $aprilProcessed = ReconciliationSession::factory()->processed()->create(['period' => '2026-04-01']);

        Livewire::actingAs(User::factory()->create())
            ->withQueryParams(['periodo' => '04/2026', 'situacao' => ['status' => ['value' => 'processed']]])
            ->test(Index::class)
            ->assertSet('tableSearch', '04/2026')
            ->assertCanSeeTableRecords([$aprilProcessed])
            ->assertCanNotSeeTableRecords([$may, $april]);
    }

    public function test_list_does_not_run_one_query_per_session(): void
    {
        ReconciliationSession::factory()->count(3)->withActiveFiles()->create();
        $user = User::factory()->create();

        $queryCountFor = function () use ($user): int {
            $queries = 0;
            DB::listen(function () use (&$queries): void {
                $queries++;
            });
            Livewire::actingAs($user)->test(Index::class)->assertOk();

            return $queries;
        };

        $withThree = $queryCountFor();

        ReconciliationSession::factory()->count(5)->withActiveFiles()->create();

        $this->assertLessThanOrEqual($withThree * 2 + 2, $queryCountFor());
    }
}
