<?php

namespace Tests\Feature\Reconciliation;

use App\Livewire\Reconciliation\LinksTable;
use App\Livewire\Reconciliation\PendingTable;
use App\Livewire\Reconciliation\Show;
use App\Models\PendingItem;
use App\Models\ReconciliationLink;
use App\Models\ReconciliationSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class PendingScreenTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    /**
     * A processed session with one item of each kind.
     *
     * @return array{session: ReconciliationSession, items: array<string, PendingItem>}
     */
    protected function processedSession(): array
    {
        $session = $this->sessionWithFiles(state: 'open');

        $this->authorization($session, 'PADARIA PERNAMBUCANA LTDA', 125040);
        $this->payment($session, 'PADARIA PERNAMBUCANA', 125040);
        $this->authorization($session, 'MERCADO BOM PRECO CENTRO', 50000);
        $doubtful = $this->payment($session, 'MERCADO BOM PRXYO', 50000);
        $this->authorization($session, 'CONSTRUTORA HORIZONTE', 300000);
        $partial = $this->payment($session, 'CONSTRUTORA HORIZONTE', 100000);
        $this->authorization($session, 'GRAFICA RAPIDA', 50000);
        $excess = $this->payment($session, 'GRAFICA RAPIDA', 65000);
        $unpaid = $this->authorization($session, 'SERRALHERIA UNIAO', 77700);
        $orphan = $this->payment($session, 'POSTO DE COMBUSTIVEL ALFA', 9900);

        $this->reconcile($session);

        $find = fn (string $id): PendingItem => PendingItem::query()->findOrFail($id);

        return ['session' => $session, 'items' => [
            'doubtful' => $find('s-'.$doubtful->suggestions()->sole()->id),
            'partial' => $find('s-'.$partial->suggestions()->sole()->id),
            'excess' => $find('s-'.$excess->suggestions()->sole()->id),
            'unmatched_authorization' => $find('a-'.$unpaid->id),
            'unmatched_payment' => $find('p-'.$orphan->id),
        ]];
    }

    public function test_page_shows_the_summary_of_the_run(): void
    {
        ['session' => $session] = $this->processedSession();

        $this->actingAs($this->operator())
            ->get(route('reconciliation.show', $session))
            ->assertOk()
            ->assertSeeLivewire(Show::class)
            ->assertSeeLivewire(PendingTable::class)
            ->assertSee(__('conciliation.reconciliation.summary.heading'))
            ->assertSee('1 (20%)')
            ->assertSee(__('conciliation.reconciliation.tabs.cartoes'));
    }

    public function test_default_list_shows_what_involves_an_authorization_and_awaits_a_decision(): void
    {
        ['session' => $session, 'items' => $items] = $this->processedSession();

        Livewire::actingAs($this->operator())
            ->test(PendingTable::class, ['sessionId' => $session->id])
            ->assertCanSeeTableRecords([$items['doubtful'], $items['partial'], $items['excess'], $items['unmatched_authorization']])
            ->assertCanNotSeeTableRecords([$items['unmatched_payment']])
            ->assertCountTableRecords(4)
            ->assertTableColumnFormattedStateSet('classification', __('conciliation.reconciliation.pending.doubtful'), $items['doubtful'])
            ->assertTableColumnStateSet('authorization_supplier', 'MERCADO BOM PRECO CENTRO', $items['doubtful'])
            ->assertTableColumnStateSet('payment_supplier', 'MERCADO BOM PRXYO', $items['doubtful'])
            ->assertSee(__('conciliation.reconciliation.columns.score_of', ['score' => $items['doubtful']->score]))
            ->assertTableColumnFormattedStateSet('authorization.amount_cents', 'R$ 3.000,00', $items['partial'])
            ->assertTableColumnFormattedStateSet('payment.amount_cents', 'R$ 1.000,00', $items['partial'])
            ->assertTableColumnFormattedStateSet('difference_cents', '-R$ 2.000,00', $items['partial'])
            ->assertTableColumnFormattedStateSet('difference_cents', 'R$ 150,00', $items['excess']);
    }

    public function test_each_quick_filter_shows_only_its_kind(): void
    {
        ['session' => $session, 'items' => $items] = $this->processedSession();
        $screen = Livewire::actingAs($this->operator())->test(PendingTable::class, ['sessionId' => $session->id]);

        foreach ($items as $classification => $item) {
            $screen->call('filterBy', $classification)
                ->assertSet('classification', $classification)
                ->assertCanSeeTableRecords([$item])
                ->assertCountTableRecords(1);
        }

        $screen->call('filterBy', 'todos')->assertCountTableRecords(4);
        $screen->call('filterBy', 'inexistente')->assertSet('classification', 'todos');
    }

    public function test_search_matches_the_supplier_on_either_side(): void
    {
        ['session' => $session, 'items' => $items] = $this->processedSession();
        $screen = Livewire::actingAs($this->operator())->test(PendingTable::class, ['sessionId' => $session->id]);

        $screen->searchTable('PRXYO')->assertCanSeeTableRecords([$items['doubtful']])->assertCountTableRecords(1);
        $screen->searchTable('SERRALHERIA')->assertCanSeeTableRecords([$items['unmatched_authorization']])->assertCountTableRecords(1);
        $screen->searchTable('')->assertCountTableRecords(4);
    }

    public function test_filter_and_search_are_kept_in_the_url(): void
    {
        ['session' => $session, 'items' => $items] = $this->processedSession();

        Livewire::actingAs($this->operator())
            ->withQueryParams(['filtro' => 'partial', 'busca' => 'HORIZONTE'])
            ->test(PendingTable::class, ['sessionId' => $session->id])
            ->assertSet('classification', 'partial')
            ->assertSet('tableSearch', 'HORIZONTE')
            ->assertCanSeeTableRecords([$items['partial']])
            ->assertCountTableRecords(1);

        Livewire::actingAs($this->operator())
            ->withQueryParams(['aba' => 'conciliados'])
            ->test(Show::class, ['session' => $session])
            ->assertSet('tab', 'conciliados')
            ->assertSeeLivewire(LinksTable::class);
    }

    public function test_reconciled_pairs_are_listed_in_their_own_tab(): void
    {
        ['session' => $session] = $this->processedSession();
        $link = ReconciliationLink::query()->sole();

        Livewire::actingAs($this->operator())
            ->test(LinksTable::class, ['sessionId' => $session->id])
            ->assertCanSeeTableRecords([$link])
            ->assertCountTableRecords(1)
            ->assertTableColumnFormattedStateSet('origin', __('conciliation.reconciliation.link_origin.automatic'), $link)
            ->assertTableColumnStateSet('authorization.supplier_name', 'PADARIA PERNAMBUCANA LTDA', $link)
            ->assertSee(__('conciliation.reconciliation.columns.score_of', ['score' => 100]));
    }

    public function test_suggestion_with_an_earlier_authorization_shows_where_it_came_from(): void
    {
        $july = $this->sessionWithFiles('2026-07-01');
        $this->runFor($july);
        $this->authorization($july, 'PADARIA PERNAMBUCANA', 50000, ['request' => 'COFFEE BREAK DA REUNIAO - 00961']);
        $untouched = $this->authorization($july, 'SERRALHERIA UNIAO', 77700);
        $august = $this->sessionWithFiles('2026-08-01', state: 'open');
        $this->authorization($august, 'PADARIA PERNAMBUCANA', 50000, ['request' => 'LANCHE DA VISITA - 00929']);
        $this->payment($august, 'PADARIA PERNAMBUCANA', 50000);

        $this->reconcile($august);

        Livewire::actingAs($this->operator())
            ->test(PendingTable::class, ['sessionId' => $august->id])
            ->assertCountTableRecords(2)
            ->assertSee('COFFEE BREAK DA REUNIAO - 00961')
            ->assertSee('LANCHE DA VISITA - 00929')
            ->assertSee(__('conciliation.reconciliation.columns.from_session', ['period' => '07/2026']))
            ->assertSee(__('conciliation.reconciliation.warnings.tie'))
            ->assertCanNotSeeTableRecords([PendingItem::query()->findOrFail('a-'.$untouched->id)]);
    }

    public function test_payment_of_a_split_obligation_says_how_many_other_rows_it_has(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $this->authorization($session, 'CONSTRUTORA HORIZONTE', 234108);
        $this->payment($session, 'CONSTRUTORA HORIZONTE', 103192, ['obligation_number' => '19634552', 'operation_code' => '12006165']);
        $this->payment($session, 'CONSTRUTORA HORIZONTE', 130916, ['obligation_number' => '19634552', 'operation_code' => '12006203']);

        $this->reconcile($session);

        Livewire::actingAs($this->operator())
            ->test(PendingTable::class, ['sessionId' => $session->id])
            ->assertSee(trans_choice('conciliation.reconciliation.columns.obligation_rows', 1, ['count' => 1]));
    }

    public function test_session_without_a_result_shows_a_notice_instead_of_the_lists(): void
    {
        $session = $this->sessionWithFiles(state: 'open');

        $this->actingAs($this->operator())
            ->get(route('reconciliation.show', $session))
            ->assertOk()
            ->assertSee(__('conciliation.reconciliation.no_result.heading'))
            ->assertSee(__('conciliation.reconciliation.no_result.open'))
            ->assertDontSeeLivewire(PendingTable::class);
    }

    public function test_guest_and_missing_session(): void
    {
        ['session' => $session] = $this->processedSession();

        $this->get(route('reconciliation.show', $session))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('reconciliation.show', 999999))->assertNotFound();
    }

    public function test_session_panel_links_to_the_reconciliation(): void
    {
        ['session' => $session] = $this->processedSession();

        $this->actingAs($this->operator())
            ->get(route('sessions.show', $session))
            ->assertSee(__('conciliation.reconciliation.open'))
            ->assertSee(route('reconciliation.show', $session))
            ->assertSee('1 de 5 autorizações conciliadas sozinhas (20%) e 3 pares aguardando decisão');

        $this->actingAs($this->operator())
            ->get(route('sessions.show', $this->sessionWithFiles(state: 'open')))
            ->assertDontSee(__('conciliation.reconciliation.open'));
    }
}
