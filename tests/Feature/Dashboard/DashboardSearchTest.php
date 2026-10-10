<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ImportSlot;
use App\Livewire\Dashboard\AuthorizationsTable;
use App\Livewire\Dashboard\DivergencesTable;
use App\Models\PendingItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class DashboardSearchTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    public function test_authorizations_are_found_by_supplier_request_or_amount(): void
    {
        $july = $this->processedSession('2026-07-01');
        $ink = $this->authorization($july, 'GRAFICA SUL', 28870, ['request' => 'TONER PARA IMPRESSORA - 00123']);
        $bread = $this->authorization($july, 'PADARIA PERNAMBUCANA', 5400, ['request' => 'COFFEE BREAK - 00456']);
        $fuel = $this->authorization($july, 'POSTO ALFA', 1200000, ['request' => 'COMBUSTIVEL - 00789']);

        $screen = Livewire::actingAs($this->operator())->test(AuthorizationsTable::class, ['scope' => 'open']);

        foreach ([
            'PADARIA' => [$bread],
            'toner' => [$ink],
            '00789' => [$fuel],
            '288,70' => [$ink],
            'R$ 54,00' => [$bread],
            '12.000,00' => [$fuel],
            'nada disso' => [],
        ] as $search => $found) {
            $screen->searchTable((string) $search)
                ->assertCountTableRecords(count($found))
                ->assertCanSeeTableRecords($found);
        }

        $screen->searchTable('288,70')->assertSet('tabTotals.authorized_cents', 28870);
    }

    public function test_authorizations_are_filtered_by_period_and_amount(): void
    {
        $july = $this->processedSession('2026-07-01');
        $early = $this->authorization($july, 'GRAFICA SUL', 10000, ['authorized_on' => '2026-07-02']);
        $middle = $this->authorization($july, 'PADARIA PERNAMBUCANA', 50000, ['authorized_on' => '2026-07-15']);
        $late = $this->authorization($july, 'POSTO ALFA', 200000, ['authorized_on' => '2026-07-28']);

        $screen = Livewire::actingAs($this->operator())->test(AuthorizationsTable::class, ['scope' => 'open']);

        $screen->filterTable('authorized', ['from' => '2026-07-10', 'until' => '2026-07-20'])
            ->assertCanSeeTableRecords([$middle])
            ->assertCanNotSeeTableRecords([$early, $late])
            ->assertSet('tabTotals.authorizations', 1)
            ->resetTableFilters()
            ->filterTable('authorized', ['from' => '2026-07-15'])
            ->assertCanSeeTableRecords([$middle, $late])
            ->assertCanNotSeeTableRecords([$early])
            ->resetTableFilters()
            ->filterTable('authorized', ['until' => '2026-07-02'])
            ->assertCanSeeTableRecords([$early])
            ->assertCanNotSeeTableRecords([$middle, $late])
            ->resetTableFilters()
            ->filterTable('authorized_amount', ['from' => '100,00', 'until' => '1.000,00'])
            ->assertCanSeeTableRecords([$early, $middle])
            ->assertCanNotSeeTableRecords([$late])
            ->assertSet('tabTotals.authorized_cents', 60000)
            ->resetTableFilters()
            ->filterTable('authorized_amount', ['from' => '500,01'])
            ->assertCanSeeTableRecords([$late])
            ->assertCanNotSeeTableRecords([$early, $middle])
            ->resetTableFilters()
            ->filterTable('authorized_amount', ['from' => 'muito'])
            ->assertCountTableRecords(3);

        Livewire::actingAs($this->operator())
            ->withQueryParams(['filtros' => ['authorized_amount' => ['until' => '100,00']]])
            ->test(AuthorizationsTable::class, ['scope' => 'open'])
            ->assertCanSeeTableRecords([$early])
            ->assertCanNotSeeTableRecords([$middle, $late])
            ->assertSee('R$ 100,00');
    }

    public function test_divergences_are_found_and_filtered_by_payment(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $fuel = $this->payment($session, 'POSTO ALFA', 7000, ['paid_on' => '2026-07-03', 'operation_code' => '11031234', 'operation_name' => 'COMBUSTIVEL']);
        $meal = $this->payment($session, 'RESTAURANTE SABOR', 28870, ['paid_on' => '2026-07-18', 'operation_name' => 'ALIMENTACAO'], ImportSlot::PaymentsSocial);
        $this->reconcile($session);
        $fuelItem = PendingItem::query()->findOrFail('p-'.$fuel->id);
        $mealItem = PendingItem::query()->findOrFail('p-'.$meal->id);

        $screen = Livewire::actingAs($this->operator())->test(DivergencesTable::class);

        foreach ([
            'RESTAURANTE' => [$mealItem],
            '11031234' => [$fuelItem],
            'combust' => [$fuelItem],
            '288,70' => [$mealItem],
            '70' => [$fuelItem],
        ] as $search => $found) {
            $screen->searchTable((string) $search)
                ->assertCountTableRecords(count($found))
                ->assertCanSeeTableRecords($found);
        }

        $screen->searchTable('')
            ->filterTable('paid', ['from' => '2026-07-10'])
            ->assertCanSeeTableRecords([$mealItem])
            ->assertCanNotSeeTableRecords([$fuelItem])
            ->resetTableFilters()
            ->filterTable('paid', ['until' => '2026-07-10'])
            ->assertCanSeeTableRecords([$fuelItem])
            ->assertCanNotSeeTableRecords([$mealItem])
            ->resetTableFilters()
            ->filterTable('paid_amount', ['from' => '100,00'])
            ->assertCanSeeTableRecords([$mealItem])
            ->assertCanNotSeeTableRecords([$fuelItem])
            ->resetTableFilters()
            ->filterTable('paid_amount', ['until' => '70,00'])
            ->assertCanSeeTableRecords([$fuelItem])
            ->assertCanNotSeeTableRecords([$mealItem]);
    }
}
