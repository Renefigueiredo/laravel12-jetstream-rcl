<?php

namespace Tests\Feature\Reconciliation;

use App\Enums\ImportSlot;
use App\Models\AuthorizationEntry;
use App\Models\PaymentEntry;
use App\Models\ReconciliationLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class ReconciliationPerformanceTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    private const TRADES = [
        'PADARIA', 'GRAFICA', 'PAPELARIA', 'MERCADO', 'POSTO', 'FARMACIA', 'DISTRIBUIDORA', 'COMERCIO', 'SERVICOS', 'TRANSPORTES',
        'LAVANDERIA', 'OFICINA', 'LIVRARIA', 'RESTAURANTE', 'CLINICA', 'LABORATORIO', 'CONSTRUTORA', 'ELETRICA', 'HIDRAULICA', 'INFORMATICA',
    ];

    private const SYLLABLES = ['BA', 'CE', 'DI', 'FO', 'GU', 'JA', 'LE', 'MI', 'NO', 'PU', 'RA', 'SE', 'TI', 'VO', 'XU'];

    public function test_ten_thousand_entries_are_reconciled_within_two_minutes(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $authorizationFile = $this->fileOf($session, ImportSlot::Authorizations)->id;
        $paymentFile = $this->fileOf($session, ImportSlot::PaymentsSaude)->id;

        $authorizations = [];
        $payments = [];

        for ($index = 0; $index < 2000; $index++) {
            $authorizations[] = [
                'import_file_id' => $authorizationFile,
                'reconciliation_session_id' => $session->id,
                'row_number' => $index + 2,
                'request' => 'PEDIDO '.$index,
                'supplier_name' => $this->supplier($index),
                'amount_cents' => 10000 + $index * 37,
                'authorized_on' => '2026-07-05',
                'payment_method' => 'PIX',
                'payment_condition' => $index % 10 === 0 ? '3x' : 'A vista',
                'identity_key' => hash('sha256', 'A'.$index),
                'raw' => '[]',
            ];
        }

        for ($index = 0; $index < 8000; $index++) {
            $payments[] = [
                'import_file_id' => $paymentFile,
                'reconciliation_session_id' => $session->id,
                'row_number' => $index + 2,
                'unit' => 'saude',
                'supplier_name' => $this->supplier($index),
                'amount_cents' => $index < 1500 ? 10000 + $index * 37 : 500 + $index * 13,
                'obligation_amount_cents' => $index < 1500 ? 10000 + $index * 37 : 500 + $index * 13,
                'paid_on' => '2026-07-20',
                'operation_code' => '11022276',
                'operation_name' => 'MATERIAL DE CONSUMO',
                'species' => 'NOTA FISCAL',
                'transaction_type' => 'PIX',
                'obligation_number' => 'OB'.$index,
                'settlement_status' => 'LIQUIDADO',
                'account_movement' => 'MV'.$index,
                'identity_key' => 'OB'.$index.'|11022276|MV'.$index,
                'raw' => '[]',
            ];
        }

        foreach (array_chunk($authorizations, 500) as $chunk) {
            AuthorizationEntry::query()->insert($chunk);
        }

        foreach (array_chunk($payments, 500) as $chunk) {
            PaymentEntry::query()->insert($chunk);
        }

        $startedAt = microtime(true);

        $run = $this->reconcile($session);

        $elapsedSeconds = microtime(true) - $startedAt;

        $this->assertSame(2000, $run->totals['authorizations']);
        $this->assertSame(8000, $run->totals['payments_compared']);
        $this->assertGreaterThanOrEqual(1400, ReconciliationLink::query()->count());
        $this->assertLessThan(120, $elapsedSeconds, 'The reconciliation took '.round($elapsedSeconds, 1).' seconds.');
    }

    /**
     * Two thousand distinct names, repeated by the payments: a trade shared by a hundred of
     * them, as "COMERCIO" is in the real files, followed by two words of their own.
     */
    private function supplier(int $index): string
    {
        $index %= 2000;

        return self::TRADES[$index % count(self::TRADES)].' '.$this->word($index).' '.$this->word($index * 31 + 7);
    }

    private function word(int $seed): string
    {
        $count = count(self::SYLLABLES);

        return self::SYLLABLES[$seed % $count].self::SYLLABLES[intdiv($seed, $count) % $count].self::SYLLABLES[intdiv($seed, $count * $count) % $count].'S';
    }
}
