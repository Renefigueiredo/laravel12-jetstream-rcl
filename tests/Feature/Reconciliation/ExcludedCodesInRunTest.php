<?php

namespace Tests\Feature\Reconciliation;

use App\Enums\SkipReason;
use App\Models\ExcludedOperationCode;
use App\Models\PendingItem;
use App\Models\ReconciliationSkip;
use App\Models\ReconciliationSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class ExcludedCodesInRunTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    public function test_payments_with_an_excluded_code_are_left_out_and_marked(): void
    {
        ExcludedOperationCode::factory()->create(['code' => '20150652']);
        ExcludedOperationCode::factory()->create(['code' => '99999999']);
        $session = $this->sessionWithFiles(state: 'open');
        $authorization = $this->authorization($session, 'PADARIA PERNAMBUCANA', 10000);
        $excluded = $this->payment($session, 'PADARIA PERNAMBUCANA', 10000, ['operation_code' => '20150652']);
        $padded = $this->payment($session, 'FOLHA DE PAGAMENTO', 500000, ['operation_code' => ' 20150652 ']);
        $kept = $this->payment($session, 'POSTO ALFA', 7000);

        $run = $this->reconcile($session);

        $this->assertNull($excluded->link);
        $this->assertSame(0, ReconciliationSuggestion::query()->count());

        $skips = ReconciliationSkip::query()->orderBy('payment_entry_id')->get();

        $this->assertSame([$excluded->id, $padded->id], $skips->pluck('payment_entry_id')->all());
        $this->assertSame([SkipReason::ExcludedCode], $skips->pluck('reason')->unique()->values()->all());
        $this->assertSame(['20150652'], $skips->pluck('operation_code')->unique()->values()->all());

        $this->assertSame(['20150652', '99999999'], $run->excluded_codes);
        $this->assertSame(['20150652' => 2], $run->totals['excluded_by_code']);
        $this->assertSame(2, $run->totals['excluded_payments']);
        $this->assertSame(1, $run->totals['payments_compared']);

        $pending = PendingItem::query()->where('reconciliation_session_id', $session->id)->pluck('classification', 'id');

        $this->assertSame(['a-'.$authorization->id => 'unmatched_authorization', 'p-'.$kept->id => 'unmatched_payment'], $pending->all());
    }

    public function test_changing_the_list_does_not_change_a_processed_session(): void
    {
        $code = ExcludedOperationCode::factory()->create(['code' => '20150652']);
        $session = $this->sessionWithFiles(state: 'open');
        $this->authorization($session, 'PADARIA PERNAMBUCANA', 10000);
        $excluded = $this->payment($session, 'PADARIA PERNAMBUCANA', 10000, ['operation_code' => '20150652']);
        $other = $this->payment($session, 'POSTO ALFA', 7000, ['operation_code' => '11018953']);

        $run = $this->reconcile($session);

        $code->delete();
        ExcludedOperationCode::factory()->create(['code' => '11018953']);

        $this->assertSame(['20150652'], $run->refresh()->excluded_codes);
        $this->assertSame([$excluded->id], ReconciliationSkip::query()->pluck('payment_entry_id')->all());
        $this->assertNull($excluded->refresh()->link);

        $second = $this->reconcile($session);

        $this->assertSame(['11018953'], $second->excluded_codes);
        $this->assertSame([$other->id], ReconciliationSkip::query()->pluck('payment_entry_id')->all());
        $this->assertNotNull($excluded->refresh()->link);
        $this->assertSame(['20150652'], $run->refresh()->excluded_codes);
    }
}
