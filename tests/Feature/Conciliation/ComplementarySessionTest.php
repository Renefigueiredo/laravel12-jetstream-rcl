<?php

namespace Tests\Feature\Conciliation;

use App\Enums\ImportAttemptStatus;
use App\Enums\ImportSlot;
use App\Models\PaymentEntry;
use App\Models\ReconciliationSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SubmitsSpreadsheets;
use Tests\TestCase;

class ComplementarySessionTest extends TestCase
{
    use RefreshDatabase;
    use SubmitsSpreadsheets;

    public function test_only_rows_missing_from_other_sessions_of_the_period_are_imported(): void
    {
        $first = ReconciliationSession::factory()->create(['period' => '2026-05-01']);
        $complementary = ReconciliationSession::factory()->create(['period' => '2026-05-01']);
        $existingRows = $this->paymentRows(200);

        $this->submit($first, ImportSlot::PaymentsSocial, $this->paymentsCsv($existingRows, 'primeiro.csv'));
        $this->submit($complementary, ImportSlot::PaymentsSocial, $this->paymentsCsv([...$existingRows, ...$this->paymentRows(15)], 'complementar.csv'));

        $file = $complementary->activeFiles()->sole();

        $this->assertSame(15, $file->rows_imported);
        $this->assertSame(200, $file->rows_skipped_existing);
        $this->assertSame(215, PaymentEntry::query()->count());
    }

    public function test_comparison_respects_the_quantity_of_identical_rows(): void
    {
        $first = ReconciliationSession::factory()->create();
        $complementary = ReconciliationSession::factory()->create();
        $row = $this->paymentRow();

        $this->submit($first, ImportSlot::PaymentsSocial, $this->paymentsCsv([$row, $row], 'a.csv'));
        $this->submit($complementary, ImportSlot::PaymentsSocial, $this->paymentsCsv([$row, $row, $row], 'b.csv'));

        $file = $complementary->activeFiles()->sole();

        $this->assertSame(1, $file->rows_imported);
        $this->assertSame(2, $file->rows_skipped_existing);
    }

    public function test_file_whose_rows_all_exist_is_accepted_with_zero_new_entries(): void
    {
        $first = ReconciliationSession::factory()->create();
        $complementary = ReconciliationSession::factory()->create();
        $rows = $this->authorizationRows(4);

        $this->submit($first, ImportSlot::Authorizations, $this->authorizationsXlsx($rows, 'a.xlsx'));
        $attempt = $this->submit($complementary, ImportSlot::Authorizations, $this->authorizationsXlsx([...$rows, $this->authorizationRow(['VALOR' => '0'])], 'b.xlsx'));

        $file = $complementary->activeFiles()->sole();

        $this->assertSame(ImportAttemptStatus::Accepted, $attempt->status);
        $this->assertSame(0, $file->rows_imported);
        $this->assertSame(4, $file->rows_skipped_existing);
        $this->assertSame(1, $file->rows_skipped_value);
    }

    public function test_replaced_files_and_other_periods_do_not_count(): void
    {
        $first = ReconciliationSession::factory()->create(['period' => '2026-05-01']);
        $otherPeriod = ReconciliationSession::factory()->create(['period' => '2026-04-01']);
        $complementary = ReconciliationSession::factory()->create(['period' => '2026-05-01']);
        $replacedRows = $this->paymentRows(3);
        $otherPeriodRows = $this->paymentRows(2);

        $this->submit($first, ImportSlot::PaymentsSocial, $this->paymentsCsv($replacedRows, 'substituido.csv'));
        $this->submit($first, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(1), 'vigente.csv'));
        $this->submit($otherPeriod, ImportSlot::PaymentsSocial, $this->paymentsCsv($otherPeriodRows, 'abril.csv'));
        $this->submit($complementary, ImportSlot::PaymentsSocial, $this->paymentsCsv([...$replacedRows, ...$otherPeriodRows], 'complementar.csv'));

        $file = $complementary->activeFiles()->sole();

        $this->assertSame(5, $file->rows_imported);
        $this->assertSame(0, $file->rows_skipped_existing);
    }

    public function test_same_payment_in_the_other_unit_does_not_count(): void
    {
        $first = ReconciliationSession::factory()->create();
        $complementary = ReconciliationSession::factory()->create();
        $rows = $this->paymentRows(2);

        $this->submit($first, ImportSlot::PaymentsSocial, $this->paymentsCsv($rows, 'social.csv'));
        $this->submit($complementary, ImportSlot::PaymentsSaude, $this->paymentsCsv($rows, 'saude.csv'));

        $this->assertSame(2, $complementary->activeFiles()->sole()->rows_imported);
    }

    public function test_replacing_a_file_compares_again_against_the_other_sessions(): void
    {
        $first = ReconciliationSession::factory()->create();
        $complementary = ReconciliationSession::factory()->create();
        $ownRows = $this->paymentRows(2);
        $rowsLoadedLaterInTheOtherSession = $this->paymentRows(2);

        $this->submit($complementary, ImportSlot::PaymentsSocial, $this->paymentsCsv($ownRows, 'antes.csv'));
        $this->submit($first, ImportSlot::PaymentsSocial, $this->paymentsCsv($rowsLoadedLaterInTheOtherSession, 'outra-sessao.csv'));
        $this->submit($complementary, ImportSlot::PaymentsSocial, $this->paymentsCsv([...$ownRows, ...$rowsLoadedLaterInTheOtherSession, $this->paymentRow()], 'depois.csv'));

        $file = $complementary->activeFiles()->sole();

        $this->assertSame(3, $file->rows_imported);
        $this->assertSame(2, $file->rows_skipped_existing);
    }
}
