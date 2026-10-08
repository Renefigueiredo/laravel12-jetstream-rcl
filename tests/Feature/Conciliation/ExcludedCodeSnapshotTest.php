<?php

namespace Tests\Feature\Conciliation;

use App\Enums\ImportAttemptStatus;
use App\Enums\ImportSlot;
use App\Models\ExcludedOperationCode;
use App\Models\PaymentEntry;
use App\Models\ReconciliationSession;
use App\Services\ExcludedCodes\ExcludedOperationCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SubmitsSpreadsheets;
use Tests\TestCase;

class ExcludedCodeSnapshotTest extends TestCase
{
    use RefreshDatabase;
    use SubmitsSpreadsheets;

    public function test_snapshot_lists_the_codes_in_ascending_order(): void
    {
        foreach (['30000000', '00123', 'ABC', '10000000'] as $code) {
            ExcludedOperationCode::factory()->create(['code' => $code]);
        }

        $this->assertSame(['00123', '10000000', '30000000', 'ABC'], app(ExcludedOperationCodes::class)->snapshot()->codes());
    }

    public function test_comparison_is_exact_after_trimming(): void
    {
        ExcludedOperationCode::factory()->create(['code' => '00123']);
        ExcludedOperationCode::factory()->create(['code' => 'ABC']);
        ExcludedOperationCode::factory()->create(['code' => '20150652']);

        $snapshot = app(ExcludedOperationCodes::class)->snapshot();

        $this->assertTrue($snapshot->contains('00123'));
        $this->assertTrue($snapshot->contains(' 00123 '));
        $this->assertTrue($snapshot->contains("20150652\u{00A0}"));
        $this->assertTrue($snapshot->contains('ABC'));
        $this->assertFalse($snapshot->contains('123'));
        $this->assertFalse($snapshot->contains('0123'));
        $this->assertFalse($snapshot->contains('abc'));
        $this->assertFalse($snapshot->contains('2015065'));
        $this->assertFalse($snapshot->contains(''));
        $this->assertFalse($snapshot->contains('   '));
        $this->assertFalse($snapshot->contains(null));
    }

    public function test_snapshot_does_not_change_when_the_list_changes(): void
    {
        $kept = ExcludedOperationCode::factory()->create(['code' => '111']);
        $codes = app(ExcludedOperationCodes::class);

        $snapshot = $codes->snapshot();

        $kept->delete();
        ExcludedOperationCode::factory()->create(['code' => '222']);

        $this->assertSame(['111'], $snapshot->codes());
        $this->assertTrue($snapshot->contains('111'));
        $this->assertFalse($snapshot->contains('222'));

        $this->assertSame(['222'], $codes->snapshot()->codes());
    }

    public function test_empty_list_gives_an_empty_snapshot(): void
    {
        $snapshot = app(ExcludedOperationCodes::class)->snapshot();

        $this->assertSame([], $snapshot->codes());
        $this->assertFalse($snapshot->contains('20150652'));
    }

    public function test_payments_with_an_excluded_code_are_still_imported(): void
    {
        ExcludedOperationCode::factory()->create(['code' => '20150652']);
        $session = ReconciliationSession::factory()->create();

        $attempt = $this->submit($session, ImportSlot::PaymentsSaude, $this->paymentsCsv([
            $this->paymentRow(['COD_OPERACAO' => '20150652']),
            $this->paymentRow(['COD_OPERACAO' => '20150652']),
            $this->paymentRow(['COD_OPERACAO' => '11018953']),
        ], 'saude.csv'));

        $this->assertSame(ImportAttemptStatus::Accepted, $attempt->status);
        $this->assertSame(3, PaymentEntry::query()->count());
        $this->assertSame(2, PaymentEntry::query()->where('operation_code', '20150652')->count());
    }
}
