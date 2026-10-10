<?php

namespace Tests\Feature\Dashboard;

use App\Enums\DifferenceTreatment;
use App\Enums\JustificationCategory;
use App\Enums\LinkOrigin;
use App\Livewire\Dashboard\AuthorizationsTable;
use App\Models\AuthorizationEntry;
use App\Services\Reconciliation\AuthorizationPanel;
use App\Services\Reconciliation\AuthorizationStatementReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class StatementTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    public function test_statement_lists_each_payment_with_the_balance_after_it(): void
    {
        $july = $this->processedSession('2026-07-01');
        $august = $this->processedSession('2026-08-01');
        $operator = $this->operator();
        $purchase = $this->authorization($july, 'GRAFICA SUL', 90000);
        $this->linkedPayment($july, $purchase, 30000, ['paid_on' => '2026-07-10'], ['treatment' => DifferenceTreatment::StillOwed, 'isInstallment' => true]);
        $this->linkedPayment($august, $purchase, 30000, ['paid_on' => '2026-08-10', 'supplier_name' => 'GRAFICA SUL LTDA'], [
            'treatment' => DifferenceTreatment::StillOwed,
            'origin' => LinkOrigin::Manual,
            'decidedBy' => $operator,
        ]);
        $untouched = $this->authorization($august, 'LIVRARIA CULTURA', 40000);

        $rows = app(AuthorizationStatementReader::class)->read($purchase->load('links.payment'));

        $this->assertSame([60000, 30000], array_map(fn (array $row): int => $row['line']->balanceAfterCents, $rows));

        Livewire::actingAs($operator)
            ->test(AuthorizationsTable::class, ['scope' => 'open'])
            ->assertSee(__('conciliation.dashboard.statement.heading'))
            ->assertSeeInOrder(['10/07/2026', 'R$ 600,00', '10/08/2026', 'GRAFICA SUL LTDA', 'R$ 300,00'])
            ->assertSee([
                __('conciliation.reconciliation.link_origin.installment'),
                __('conciliation.reconciliation.link_origin.manual'),
                $operator->name,
                __('conciliation.units.saude'),
                $july->label(),
                $august->label(),
            ])
            ->assertSee(__('conciliation.dashboard.statement.empty'));

        $this->assertSame([], app(AuthorizationStatementReader::class)->read($untouched->load('links.payment')));
    }

    public function test_writeoffs_appear_as_lines_of_their_own(): void
    {
        $july = $this->processedSession('2026-07-01');

        $withDiscount = $this->authorization($july, 'PAPELARIA CENTRAL', 100000);
        $this->linkedPayment($july, $withDiscount, 90000, link: [
            'treatment' => DifferenceTreatment::Discount,
            'justificationCategory' => JustificationCategory::CommercialDiscount,
            'justification' => 'Desconto negociado na entrega.',
        ]);
        $withinTolerance = $this->authorization($july, 'PADARIA PERNAMBUCANA', 10000);
        $this->linkedPayment($july, $withinTolerance, 9970);
        $overpaid = $this->authorization($july, 'RESTAURANTE SABOR', 20000);
        $this->linkedPayment($july, $overpaid, 30000, link: ['treatment' => DifferenceTreatment::Overpayment]);
        $created = AuthorizationEntry::factory()->create([
            'import_file_id' => null,
            'reconciliation_session_id' => $july->id,
            'supplier_name' => 'POSTO ALFA',
            'amount_cents' => 7000,
            'created_by' => $this->administrator()->id,
        ]);
        $this->linkedPayment($july, $created, 7000);

        Livewire::actingAs($this->operator())
            ->test(AuthorizationsTable::class, ['scope' => 'reconciled'])
            ->assertCountTableRecords(4)
            ->assertSee([
                __('conciliation.dashboard.statement.kinds.discount'),
                'Desconto negociado na entrega.',
                'R$ 100,00',
                __('conciliation.dashboard.statement.kinds.tolerance_writeoff'),
                'R$ 0,30',
                __('conciliation.dashboard.statement.kinds.overpayment'),
                __('conciliation.reconciliation.link_origin.created'),
                __('conciliation.reconciliation.details.created_in_reconciliation'),
            ]);
    }

    public function test_last_balance_of_every_statement_is_the_stored_balance(): void
    {
        $july = $this->processedSession('2026-07-01');
        $august = $this->processedSession('2026-08-01');

        $instalments = $this->authorization($july, 'GRAFICA SUL', 100000);
        $this->linkedPayment($july, $instalments, 33333, link: ['treatment' => DifferenceTreatment::StillOwed]);
        $this->linkedPayment($august, $instalments, 33333, link: ['treatment' => DifferenceTreatment::StillOwed]);
        $this->linkedPayment($august, $instalments, 33334, ['paid_on' => '2026-08-25']);

        $partial = $this->authorization($july, 'PAPELARIA CENTRAL', 50000);
        $this->linkedPayment($august, $partial, 12345, link: ['treatment' => DifferenceTreatment::StillOwed]);

        $discount = $this->authorization($july, 'POSTO ALFA', 100000);
        $this->linkedPayment($july, $discount, 40000, link: ['treatment' => DifferenceTreatment::StillOwed]);
        $this->linkedPayment($august, $discount, 50000, link: [
            'treatment' => DifferenceTreatment::Discount,
            'justificationCategory' => JustificationCategory::CommercialDiscount,
            'justification' => 'Desconto.',
        ]);

        $tolerance = $this->authorization($july, 'PADARIA PERNAMBUCANA', 10000);
        $this->linkedPayment($july, $tolerance, 5000, link: ['treatment' => DifferenceTreatment::StillOwed]);
        $this->linkedPayment($august, $tolerance, 4960);

        $surcharge = $this->authorization($august, 'LIVRARIA CULTURA', 50000);
        $this->linkedPayment($august, $surcharge, 52000, link: [
            'treatment' => DifferenceTreatment::AcceptedSurcharge,
            'justificationCategory' => JustificationCategory::Freight,
            'justification' => 'Frete.',
        ]);

        $overpaid = $this->authorization($august, 'RESTAURANTE SABOR', 20000);
        $this->linkedPayment($august, $overpaid, 30000, link: ['treatment' => DifferenceTreatment::Overpayment]);

        $this->authorization($august, 'SEM PAGAMENTO', 7700);

        $reader = app(AuthorizationStatementReader::class);
        $authorizations = app(AuthorizationPanel::class)->query()->with(['state', 'links.payment'])->get();

        $this->assertCount(7, $authorizations);

        foreach ($authorizations as $authorization) {
            $rows = $reader->read($authorization);
            $last = $rows === [] ? $authorization->amount_cents : $rows[array_key_last($rows)]['line']->balanceAfterCents;

            $this->assertSame($authorization->balanceCents(), $last, $authorization->supplier_name);
        }
    }

    public function test_a_page_of_statements_does_not_query_per_line(): void
    {
        $july = $this->processedSession('2026-07-01');

        foreach (range(1, 20) as $index) {
            $authorization = $this->authorization($july, 'FORNECEDOR '.$index, 90000);
            $this->linkedPayment($july, $authorization, 30000, link: ['treatment' => DifferenceTreatment::StillOwed]);
            $this->linkedPayment($july, $authorization, 30000, link: ['treatment' => DifferenceTreatment::StillOwed]);
        }

        $operator = $this->operator();
        $this->actingAs($operator);

        DB::enableQueryLog();
        Livewire::test(AuthorizationsTable::class, ['scope' => 'open'])->assertCountTableRecords(20);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(30, $queries, 'The list ran '.$queries.' queries.');
    }
}
