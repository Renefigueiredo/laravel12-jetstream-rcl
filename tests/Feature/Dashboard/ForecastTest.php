<?php

namespace Tests\Feature\Dashboard;

use App\Actions\Conciliation\RemoveInstallmentPlan;
use App\Actions\Conciliation\RemoveLink;
use App\Actions\Conciliation\ReopenSession;
use App\Actions\Conciliation\SaveInstallmentPlan;
use App\Livewire\Dashboard\AuthorizationsTable;
use App\Livewire\Dashboard\Show;
use App\Models\AuthorizationForecast;
use App\Services\Reconciliation\AuthorizationPanel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class ForecastTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    public function test_an_instalment_not_paid_in_a_processed_month_is_overdue(): void
    {
        $july = $this->sessionWithFiles('2026-07-01', state: 'open');
        $purchase = $this->authorization($july, 'GRAFICA SUL', 90000, ['payment_condition' => '30/60/90 dias']);
        $atOnce = $this->authorization($july, 'PADARIA PERNAMBUCANA', 10000, ['payment_condition' => 'A vista']);
        $this->reconcile($july);

        $forecast = $purchase->forecast;

        $this->assertSame([3, 0, 0], [$forecast->expected_count, $forecast->paid_count, $forecast->overdue_count]);
        $this->assertSame('2026-08-01', $forecast->next_expected_month->toDateString());
        $this->assertFalse($forecast->from_plan);
        $this->assertNull($atOnce->forecast);
        $this->assertSame(0, app(AuthorizationPanel::class)->alerts()['overdue']);

        $august = $this->sessionWithFiles('2026-08-01', state: 'open');
        $first = $this->payment($august, 'GRAFICA SUL', 30000);
        $this->reconcile($august);

        $forecast->refresh();

        $this->assertTrue($first->link->is_installment);
        $this->assertSame([1, 0], [$forecast->paid_count, $forecast->overdue_count]);
        $this->assertSame('2026-09-01', $forecast->next_expected_month->toDateString());

        $september = $this->sessionWithFiles('2026-09-01', state: 'open');
        $this->reconcile($september);

        $forecast->refresh();

        $this->assertSame([1, 1], [$forecast->paid_count, $forecast->overdue_count]);
        $this->assertSame(1, app(AuthorizationPanel::class)->alerts()['overdue']);

        Livewire::actingAs($this->operator())
            ->test(Show::class)
            ->assertSee(trans_choice('conciliation.dashboard.alerts.overdue', 1, ['count' => 1]))
            ->assertSeeHtml('aba=abertas');

        Livewire::actingAs($this->operator())
            ->test(AuthorizationsTable::class, ['scope' => 'open'])
            ->assertSee([
                trans_choice('conciliation.dashboard.forecast.overdue_count', 1, ['count' => 1]),
                __('conciliation.dashboard.forecast.heading'),
                __('conciliation.dashboard.forecast.from_condition'),
                __('conciliation.dashboard.forecast.expected_in', ['month' => '09/2026']),
                __('conciliation.dashboard.forecast.expected_in', ['month' => '10/2026']),
                __('conciliation.dashboard.installment_status.overdue'),
                __('conciliation.dashboard.installment_status.open'),
            ])
            ->assertCountTableRecords(2)
            ->filterTable('overdue')
            ->assertCountTableRecords(1)
            ->assertCanSeeTableRecords([$purchase])
            ->assertCanNotSeeTableRecords([$atOnce]);

        $this->assertSame(
            [$purchase->id, $atOnce->id],
            Livewire::actingAs($this->operator())->test(AuthorizationsTable::class, ['scope' => 'open'])->instance()->getTableRecords()->pluck('id')->all(),
        );
    }

    public function test_forecast_follows_links_plans_and_reopening(): void
    {
        $july = $this->sessionWithFiles('2026-07-01', state: 'open');
        $purchase = $this->authorization($july, 'GRAFICA SUL', 90000, ['payment_condition' => '3x']);
        $payment = $this->payment($july, 'GRAFICA SUL', 30000);
        $this->reconcile($july);
        $operator = $this->operator();

        $this->assertSame(1, $purchase->forecast->paid_count);

        app(RemoveLink::class)->handle($operator, $payment->link);

        $this->assertSame(0, $purchase->forecast()->first()->paid_count);

        app(SaveInstallmentPlan::class)->handle($operator, $purchase, [
            ['amount_cents' => 50000, 'expected_month' => '2026-07'],
            ['amount_cents' => 40000, 'expected_month' => '2026-12'],
        ]);

        $forecast = $purchase->forecast()->first();

        $this->assertTrue($forecast->from_plan);
        $this->assertSame([2, 0, 1], [$forecast->expected_count, $forecast->paid_count, $forecast->overdue_count]);

        app(RemoveInstallmentPlan::class)->handle($operator, $purchase);

        $forecast = $purchase->forecast()->first();

        $this->assertFalse($forecast->from_plan);
        $this->assertSame([3, 0], [$forecast->expected_count, $forecast->overdue_count]);

        $august = $this->sessionWithFiles('2026-08-01', state: 'open');
        $this->reconcile($august);

        $this->assertSame(1, $purchase->forecast()->first()->overdue_count);

        app(ReopenSession::class)->handle($operator, $august);

        $this->assertSame(0, $purchase->forecast()->first()->overdue_count);
    }

    public function test_a_settled_authorization_has_nothing_overdue(): void
    {
        $july = $this->sessionWithFiles('2026-07-01', state: 'open');
        $purchase = $this->authorization($july, 'GRAFICA SUL', 90000, ['payment_condition' => '3x']);
        $this->payment($july, 'GRAFICA SUL', 90000);
        $this->reconcile($july);
        $this->reconcile($this->sessionWithFiles('2026-12-01', state: 'open'));

        $forecast = $purchase->forecast()->first();

        $this->assertSame(0, $forecast->overdue_count);
        $this->assertNull($forecast->next_expected_month);
        $this->assertSame(0, app(AuthorizationPanel::class)->alerts()['overdue']);
    }

    public function test_command_rebuilds_the_forecast(): void
    {
        $july = $this->sessionWithFiles('2026-07-01', state: 'open');
        $purchase = $this->authorization($july, 'GRAFICA SUL', 90000, ['payment_condition' => '3x']);
        $this->authorization($july, 'PADARIA PERNAMBUCANA', 10000);
        $this->payment($july, 'GRAFICA SUL', 30000);
        $this->reconcile($july);
        $this->reconcile($this->sessionWithFiles('2026-09-01', state: 'open'));

        $before = $purchase->forecast->only(['expected_count', 'paid_count', 'overdue_count']);

        AuthorizationForecast::query()->delete();

        $this->artisan('conciliation:refresh-forecasts')
            ->expectsOutput(__('conciliation.dashboard.forecast.refreshed', ['count' => 1]))
            ->assertSuccessful();

        $this->assertSame(['expected_count' => 3, 'paid_count' => 1, 'overdue_count' => 1], $before);
        $this->assertSame($before, $purchase->forecast()->first()->only(['expected_count', 'paid_count', 'overdue_count']));
    }
}
