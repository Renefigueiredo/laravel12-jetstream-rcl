<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ImportSlot;
use App\Livewire\Dashboard\AuthorizationsTable;
use App\Livewire\Dashboard\Show;
use App\Models\AuthorizationEntry;
use App\Models\AuthorizationState;
use App\Models\PaymentEntry;
use App\Models\ReconciliationLink;
use App\Services\Reconciliation\AuthorizationStatementReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class DashboardPerformanceTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    private const AUTHORIZATIONS = 50000;

    public function test_panel_opens_within_three_seconds_with_fifty_thousand_authorizations(): void
    {
        $session = $this->processedSession('2026-07-01');
        $run = $session->runs()->sole();
        $authorizationFile = $this->fileOf($session, ImportSlot::Authorizations)->id;
        $paymentFile = $this->fileOf($session, ImportSlot::PaymentsSaude)->id;
        $now = now()->toDateTimeString();

        foreach (array_chunk(range(1, self::AUTHORIZATIONS), 1000) as $ids) {
            $authorizations = [];
            $payments = [];
            $links = [];
            $states = [];

            foreach ($ids as $id) {
                $amount = 10000 + ($id % 900) * 100;
                $paid = match ($id % 5) {
                    0 => [],
                    1, 2 => [$amount],
                    default => [intdiv($amount, 2)],
                };

                $authorizations[] = [
                    'id' => $id,
                    'import_file_id' => $authorizationFile,
                    'reconciliation_session_id' => $session->id,
                    'row_number' => $id + 1,
                    'request' => 'PEDIDO '.$id,
                    'supplier_name' => 'FORNECEDOR '.($id % 3000),
                    'amount_cents' => $amount,
                    'authorized_on' => '2026-07-05',
                    'payment_method' => 'PIX',
                    'payment_condition' => $id % 50 === 0 ? '2x' : 'A vista',
                    'identity_key' => hash('sha256', 'A'.$id),
                    'raw' => '[]',
                ];

                foreach ($paid as $cents) {
                    $payments[] = [
                        'id' => $id,
                        'import_file_id' => $paymentFile,
                        'reconciliation_session_id' => $session->id,
                        'row_number' => $id + 1,
                        'unit' => 'saude',
                        'supplier_name' => 'FORNECEDOR '.($id % 3000),
                        'amount_cents' => $cents,
                        'obligation_amount_cents' => $cents,
                        'paid_on' => '2026-07-20',
                        'operation_code' => '11022276',
                        'operation_name' => 'MATERIAL DE CONSUMO',
                        'species' => 'NOTA FISCAL',
                        'transaction_type' => 'PIX',
                        'obligation_number' => 'OB'.$id,
                        'settlement_status' => 'LIQUIDADO',
                        'account_movement' => 'MV'.$id,
                        'identity_key' => 'OB'.$id,
                        'raw' => '[]',
                    ];

                    $links[] = [
                        'authorization_entry_id' => $id,
                        'payment_entry_id' => $id,
                        'reconciliation_run_id' => $run->id,
                        'origin' => 'automatic',
                        'is_installment' => false,
                        'difference_type' => $cents === $amount ? 'exact' : 'partial',
                        'difference_cents' => $cents - $amount,
                        'excess_cents' => 0,
                        'treatment' => $cents === $amount ? null : 'still_owed',
                        'discount_cents' => 0,
                        'tolerance_writeoff_cents' => 0,
                        'paid_before_authorization' => false,
                        'card_mismatch' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $states[] = [
                        'authorization_entry_id' => $id,
                        'links_count' => 1,
                        'paid_cents' => $cents,
                        'discount_cents' => 0,
                        'writeoff_cents' => 0,
                        'balance_cents' => $amount - $cents,
                        'status' => $cents === $amount ? 'reconciled' : 'partial',
                        'updated_at' => $now,
                    ];
                }
            }

            AuthorizationEntry::query()->insert($authorizations);

            foreach (array_chunk($payments, 500) as $chunk) {
                PaymentEntry::query()->insert($chunk);
            }

            foreach (array_chunk($links, 500) as $chunk) {
                ReconciliationLink::query()->insert($chunk);
            }

            AuthorizationState::query()->insert($states);
        }

        $operator = $this->operator();
        $this->actingAs($operator);

        $startedAt = microtime(true);

        $page = Livewire::test(Show::class);
        $list = Livewire::test(AuthorizationsTable::class, ['scope' => 'open']);

        $elapsedSeconds = microtime(true) - $startedAt;

        $page->assertSet('totals.authorizations', self::AUTHORIZATIONS)
            ->assertSet('totals.reconciled', 20000)
            ->assertSet('totals.partial', 20000)
            ->assertSet('totals.open', 10000);
        $list->assertSet('tabTotals.authorizations', 30000);

        $this->assertLessThan(3, $elapsedSeconds, 'The panel took '.round($elapsedSeconds, 2).' seconds.');

        $startedAt = microtime(true);

        $rows = app(AuthorizationStatementReader::class)->read(AuthorizationEntry::query()->with('links.payment')->findOrFail(3));

        $this->assertCount(1, $rows);
        $this->assertLessThan(1, microtime(true) - $startedAt);
    }
}
