<?php

namespace Tests\Feature\Reconciliation;

use App\Livewire\Reconciliation\ExcludedTable;
use App\Models\ExcludedOperationCode;
use App\Models\ReconciliationSkip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class ExcludedTabTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    public function test_tab_shows_the_codes_of_the_run_and_the_payments_each_one_left_out(): void
    {
        foreach (['20150652', '11024465', '99999999'] as $code) {
            ExcludedOperationCode::factory()->create(['code' => $code]);
        }

        $session = $this->sessionWithFiles(state: 'open');
        $this->payment($session, 'FOLHA DE PAGAMENTO', 900000, ['operation_code' => '20150652']);
        $this->payment($session, 'FOLHA DE PAGAMENTO', 800000, ['operation_code' => '20150652']);
        $fee = $this->payment($session, 'BANCO EMISSOR', 1350, ['operation_code' => '11024465']);
        $this->payment($session, 'POSTO ALFA', 7000);

        $this->reconcile($session);

        ExcludedOperationCode::query()->where('code', '99999999')->delete();

        $screen = Livewire::actingAs($this->operator())->test(ExcludedTable::class, ['sessionId' => $session->id]);

        $this->assertSame(['20150652' => 2, '11024465' => 1, '99999999' => 0], $screen->instance()->codes);

        $screen->assertCountTableRecords(3)
            ->assertSee('FOLHA DE PAGAMENTO')
            ->assertDontSee('POSTO ALFA')
            ->filterTable('operation_code', '11024465')
            ->assertCanSeeTableRecords([ReconciliationSkip::query()->where('payment_entry_id', $fee->id)->sole()])
            ->assertCountTableRecords(1);
    }

    public function test_tab_is_empty_when_no_code_was_in_effect(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $this->payment($session, 'POSTO ALFA', 7000);

        $this->reconcile($session);

        Livewire::actingAs($this->operator())
            ->test(ExcludedTable::class, ['sessionId' => $session->id])
            ->assertSee(__('conciliation.reconciliation.excluded.no_codes'))
            ->assertCountTableRecords(0);
    }
}
