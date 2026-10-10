<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AuthorizationStatus;
use App\Enums\DifferenceTreatment;
use App\Livewire\Dashboard\AuthorizationsTable;
use App\Livewire\Dashboard\Show;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    public function test_dashboard_is_the_first_screen_for_operators_and_administrators(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $operator = $this->operator();

        $this->post('/login', ['email' => $operator->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->actingAs($operator)->get(route('dashboard'))
            ->assertOk()
            ->assertSeeLivewire(Show::class)
            ->assertSee(__('conciliation.dashboard.summary.heading'));

        $this->actingAs($this->administrator())->get(route('dashboard'))->assertOk();
    }

    public function test_without_a_processed_session_the_panel_points_to_the_sessions(): void
    {
        Livewire::actingAs($this->operator())
            ->test(Show::class)
            ->assertSee(__('conciliation.dashboard.no_session.heading'))
            ->assertSeeHtml(route('sessions.index'))
            ->assertSet('totals.authorizations', 0);

        $this->processedSession('2026-07-01');

        Livewire::actingAs($this->operator())
            ->test(Show::class)
            ->assertDontSee(__('conciliation.dashboard.no_session.heading'));
    }

    public function test_summary_and_tabs(): void
    {
        $july = $this->processedSession('2026-07-01');
        $august = $this->processedSession('2026-08-01');
        $purchase = $this->authorization($july, 'GRAFICA SUL', 90000);
        $this->linkedPayment($july, $purchase, 30000, link: ['treatment' => DifferenceTreatment::StillOwed]);
        $this->linkedPayment($august, $purchase, 30000, link: ['treatment' => DifferenceTreatment::StillOwed]);
        $settled = $this->authorization($august, 'PADARIA PERNAMBUCANA', 10000);
        $this->linkedPayment($august, $settled, 10000);

        Livewire::actingAs($this->operator())
            ->test(Show::class)
            ->assertSet('tab', 'abertas')
            ->assertSee(['R$ 1.000,00', 'R$ 700,00', 'R$ 300,00'])
            ->assertSeeLivewire(AuthorizationsTable::class)
            ->call('showTab', 'conciliadas')
            ->assertSet('tab', 'conciliadas')
            ->call('showTab', 'nao-existe')
            ->assertSet('tab', 'conciliadas');

        Livewire::actingAs($this->operator())
            ->withQueryParams(['aba' => 'qualquer'])
            ->test(Show::class)
            ->assertSet('tab', 'abertas');

        Livewire::actingAs($this->operator())
            ->withQueryParams(['aba' => 'conciliadas'])
            ->test(Show::class)
            ->assertSet('tab', 'conciliadas');
    }

    public function test_an_authorization_paid_in_two_sessions_is_one_line(): void
    {
        $july = $this->processedSession('2026-07-01');
        $august = $this->processedSession('2026-08-01');
        $purchase = $this->authorization($july, 'GRAFICA SUL', 90000, ['payment_condition' => '3x']);
        $this->linkedPayment($july, $purchase, 30000, link: ['treatment' => DifferenceTreatment::StillOwed]);
        $this->linkedPayment($august, $purchase, 30000, link: ['treatment' => DifferenceTreatment::StillOwed]);
        $settled = $this->authorization($august, 'PADARIA PERNAMBUCANA', 10000);
        $this->linkedPayment($august, $settled, 10000);
        $untouched = $this->authorization($august, 'LIVRARIA CULTURA', 40000, ['card' => '0798']);

        Livewire::actingAs($this->operator())
            ->test(AuthorizationsTable::class, ['scope' => 'open'])
            ->assertCountTableRecords(2)
            ->assertCanSeeTableRecords([$purchase, $untouched])
            ->assertCanNotSeeTableRecords([$settled])
            ->assertSee(['GRAFICA SUL', 'R$ 900,00', 'R$ 600,00', 'R$ 300,00', '3x (3 parcelas previstas)', $july->label()])
            ->assertSet('tabTotals.authorizations', 2)
            ->assertSet('tabTotals.authorized_cents', 130000)
            ->assertSet('tabTotals.balance_cents', 70000);

        Livewire::actingAs($this->operator())
            ->test(AuthorizationsTable::class, ['scope' => 'reconciled'])
            ->assertCountTableRecords(1)
            ->assertCanSeeTableRecords([$settled])
            ->assertTableFilterHidden('status');
    }

    public function test_search_and_filters_narrow_the_list_and_the_totals_of_the_tab(): void
    {
        $july = $this->processedSession('2026-07-01');
        $august = $this->processedSession('2026-08-01');
        $partial = $this->authorization($july, 'GRAFICA SUL', 90000);
        $this->linkedPayment($july, $partial, 30000, link: ['treatment' => DifferenceTreatment::StillOwed]);
        $onCard = $this->authorization($august, 'LIVRARIA CULTURA', 40000, ['card' => '0798']);
        $plain = $this->authorization($august, 'POSTO ALFA', 5000);

        $screen = Livewire::actingAs($this->operator())->test(AuthorizationsTable::class, ['scope' => 'open']);

        $screen->searchTable('LIVRARIA')
            ->assertCanSeeTableRecords([$onCard])
            ->assertCanNotSeeTableRecords([$partial, $plain])
            ->assertSet('tabTotals.authorizations', 1)
            ->assertSet('tabTotals.balance_cents', 40000);

        $screen->searchTable('')
            ->filterTable('status', AuthorizationStatus::Partial->value)
            ->assertCanSeeTableRecords([$partial])
            ->assertCanNotSeeTableRecords([$onCard, $plain])
            ->assertSet('tabTotals.balance_cents', 60000)
            ->resetTableFilters()
            ->filterTable('status', AuthorizationStatus::Open->value)
            ->assertCanSeeTableRecords([$onCard, $plain])
            ->assertCanNotSeeTableRecords([$partial])
            ->resetTableFilters()
            ->filterTable('reconciliation_session_id', $july->id)
            ->assertCanSeeTableRecords([$partial])
            ->assertCanNotSeeTableRecords([$onCard, $plain])
            ->resetTableFilters()
            ->filterTable('card', '0798')
            ->assertCanSeeTableRecords([$onCard])
            ->assertCanNotSeeTableRecords([$partial, $plain]);

        Livewire::actingAs($this->operator())
            ->withQueryParams(['busca' => 'POSTO'])
            ->test(AuthorizationsTable::class, ['scope' => 'open'])
            ->assertCanSeeTableRecords([$plain])
            ->assertCanNotSeeTableRecords([$partial]);
    }

    public function test_entries_of_sessions_not_processed_stay_out(): void
    {
        $this->processedSession('2026-07-01');
        $hidden = $this->authorization($this->sessionWithFiles('2026-08-01', state: 'open'), 'SESSAO ABERTA', 40000);

        Livewire::actingAs($this->operator())
            ->test(AuthorizationsTable::class, ['scope' => 'open'])
            ->assertCanNotSeeTableRecords([$hidden])
            ->assertCountTableRecords(0);

        $this->assertInstanceOf(User::class, $this->operator());
    }
}
