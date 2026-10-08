<?php

namespace Tests\Feature\Conciliation;

use App\Enums\ImportAttemptStatus;
use App\Enums\ImportSlot;
use App\Models\PaymentEntry;
use App\Models\ReconciliationSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SubmitsSpreadsheets;
use Tests\TestCase;

class ImportPerformanceTest extends TestCase
{
    use RefreshDatabase;
    use SubmitsSpreadsheets;

    public function test_ten_thousand_rows_are_validated_and_imported_within_a_minute(): void
    {
        $session = ReconciliationSession::factory()->create();
        $upload = $this->paymentsCsv($this->paymentRows(10000), 'saude.csv');

        $memoryBefore = memory_get_peak_usage(true);
        $startedAt = microtime(true);

        $attempt = $this->submit($session, ImportSlot::PaymentsSaude, $upload);

        $elapsedSeconds = microtime(true) - $startedAt;
        $memoryGrowthMb = (memory_get_peak_usage(true) - $memoryBefore) / 1024 / 1024;

        $this->assertSame(ImportAttemptStatus::Accepted, $attempt->status);
        $this->assertSame(10000, PaymentEntry::query()->count());
        $this->assertLessThan(60, $elapsedSeconds, 'The import took '.round($elapsedSeconds, 1).' seconds.');
        $this->assertLessThan(64, $memoryGrowthMb, 'The import grew memory by '.round($memoryGrowthMb, 1).' MB.');
    }
}
