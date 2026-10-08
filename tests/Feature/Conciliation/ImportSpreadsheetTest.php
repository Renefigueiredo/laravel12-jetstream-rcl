<?php

namespace Tests\Feature\Conciliation;

use App\Actions\Conciliation\ActionRefusedException;
use App\Enums\AuditAction;
use App\Enums\ImportAttemptStatus;
use App\Enums\ImportFileStatus;
use App\Enums\ImportSlot;
use App\Enums\OperatingUnit;
use App\Models\AuditLog;
use App\Models\AuthorizationEntry;
use App\Models\ImportFile;
use App\Models\PaymentEntry;
use App\Models\ReconciliationSession;
use App\Models\User;
use App\Services\Import\Layouts\PaymentsLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SubmitsSpreadsheets;
use Tests\TestCase;

class ImportSpreadsheetTest extends TestCase
{
    use RefreshDatabase;
    use SubmitsSpreadsheets;

    public function test_valid_file_loads_the_slot(): void
    {
        $session = ReconciliationSession::factory()->create();

        $attempt = $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(5), 'liquidacao-social.csv'));

        $this->assertSame(ImportAttemptStatus::Accepted, $attempt->status);
        $this->assertSame(100, $attempt->progress);

        $file = $session->activeFiles()->sole();

        $this->assertSame($attempt->import_file_id, $file->id);
        $this->assertSame(ImportSlot::PaymentsSocial, $file->slot);
        $this->assertSame('liquidacao-social.csv', $file->original_name);
        $this->assertSame(5, $file->rows_imported);
        $this->assertSame(0, $file->rows_skipped_value);
        $this->assertSame($session->created_by, $file->uploaded_by);
        $this->assertSame(5, PaymentEntry::query()->where('import_file_id', $file->id)->count());
    }

    public function test_rows_with_zero_or_negative_value_are_skipped_and_counted(): void
    {
        $session = ReconciliationSession::factory()->create();
        $rows = [
            ...$this->paymentRows(97),
            $this->paymentRow(['VL_RECEBIDO' => '0']),
            $this->paymentRow(['VL_RECEBIDO' => '-12.5']),
            $this->paymentRow(['VL_RECEBIDO' => '0', 'CEDENTE' => '']),
        ];

        $this->submit($session, ImportSlot::PaymentsSaude, $this->paymentsCsv($rows));

        $file = $session->activeFiles()->sole();

        $this->assertSame(97, $file->rows_imported);
        $this->assertSame(3, $file->rows_skipped_value);
        $this->assertSame(97, PaymentEntry::query()->count());
    }

    public function test_values_in_brazilian_and_international_formats_become_the_same_cents(): void
    {
        $session = ReconciliationSession::factory()->create();

        $this->submit($session, ImportSlot::Authorizations, $this->authorizationsXlsx([
            $this->authorizationRow(['VALOR' => '1.234,56']),
            $this->authorizationRow(['VALOR' => '1,234.56']),
            $this->authorizationRow(['VALOR' => '1234.56']),
            $this->authorizationRow(['VALOR' => 1234.56]),
        ]));

        $this->assertSame([123456, 123456, 123456, 123456], AuthorizationEntry::query()->orderBy('row_number')->pluck('amount_cents')->all());
    }

    public function test_erp_csv_in_windows_1252_with_erp_dates_is_accepted(): void
    {
        $session = ReconciliationSession::factory()->create();

        $this->submit($session, ImportSlot::PaymentsSaude, $this->paymentsCsv([
            $this->paymentRow(['CEDENTE' => 'CONCEIÇÃO SERVIÇOS LTDA', 'DT_LIQUIDACAO' => '31-MAY-26', 'VL_RECEBIDO' => '12229.76']),
        ], 'saude.csv', 'Windows-1252'));

        $entry = PaymentEntry::query()->sole();

        $this->assertSame('CONCEIÇÃO SERVIÇOS LTDA', $entry->supplier_name);
        $this->assertSame('2026-05-31', $entry->paid_on->toDateString());
        $this->assertSame(1222976, $entry->amount_cents);
        $this->assertSame(OperatingUnit::Saude, $entry->unit);
        $this->assertSame('CONCEIÇÃO SERVIÇOS LTDA', $entry->raw['CEDENTE']);
        $this->assertCount(24, $entry->raw);
    }

    public function test_unit_comes_from_the_slot(): void
    {
        $session = ReconciliationSession::factory()->create();

        $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(1), 'a.csv'));
        $this->submit($session, ImportSlot::PaymentsSaude, $this->paymentsCsv($this->paymentRows(1), 'b.csv'));

        $this->assertSame(1, PaymentEntry::query()->where('unit', OperatingUnit::Social)->count());
        $this->assertSame(1, PaymentEntry::query()->where('unit', OperatingUnit::Saude)->count());
    }

    public function test_identical_rows_in_the_same_file_are_separate_entries(): void
    {
        $session = ReconciliationSession::factory()->create();
        $row = $this->paymentRow();

        $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv([$row, $row, $row]));

        $this->assertSame(3, PaymentEntry::query()->count());
        $this->assertSame([2, 3, 4], PaymentEntry::query()->orderBy('row_number')->pluck('row_number')->all());
    }

    public function test_exact_copy_is_stored_on_the_private_disk_and_can_be_downloaded(): void
    {
        $session = ReconciliationSession::factory()->create();
        $upload = $this->paymentsCsv($this->paymentRows(2));
        $originalBytes = file_get_contents($upload->getPathname());

        $this->submit($session, ImportSlot::PaymentsSocial, $upload);

        $file = $session->activeFiles()->sole();

        Storage::disk('local')->assertExists($file->path);
        $this->assertSame($originalBytes, Storage::disk('local')->get($file->path));
        $this->assertSame([], Storage::disk('local')->files('conciliation/incoming'));

        $response = $this->actingAs($session->creator)->get(route('sessions.files.download', [$session, $file]));

        $response->assertOk()->assertDownload('pagamentos.csv');
        $this->assertSame($originalBytes, $response->streamedContent());
    }

    public function test_download_requires_login_and_a_file_of_the_session(): void
    {
        $session = ReconciliationSession::factory()->create();
        $otherSession = ReconciliationSession::factory()->create();
        $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(1)));
        $file = $session->activeFiles()->sole();

        $this->get(route('sessions.files.download', [$session, $file]))->assertRedirect(route('login'));
        $this->actingAs($session->creator)->get(route('sessions.files.download', [$otherSession, $file]))->assertNotFound();
    }

    public function test_new_valid_upload_replaces_the_previous_file_and_is_audited(): void
    {
        $session = ReconciliationSession::factory()->create();

        $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(2), 'primeiro.csv'));
        $first = $session->activeFiles()->sole();

        $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(3), 'segundo.csv'));

        $first->refresh();
        $second = $session->activeFiles()->sole();

        $this->assertSame(ImportFileStatus::Replaced, $first->status);
        $this->assertNotNull($first->replaced_at);
        $this->assertSame('segundo.csv', $second->original_name);
        $this->assertSame(3, $second->rows_imported);
        $this->assertSame(2, PaymentEntry::query()->where('import_file_id', $first->id)->count());
        Storage::disk('local')->assertExists($first->path);

        $log = AuditLog::query()->where('action', AuditAction::FileReplaced)->sole();

        $this->assertSame($session->id, $log->auditable_id);
        $this->assertSame('primeiro.csv', $log->before['file']);
        $this->assertSame('segundo.csv', $log->after['file']);
        $this->assertSame(3, $log->after['entries']);
    }

    public function test_first_upload_is_not_audited_as_a_replacement(): void
    {
        $session = ReconciliationSession::factory()->create();

        $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(2)));

        $this->assertSame(0, AuditLog::query()->where('action', AuditAction::FileReplaced)->count());
    }

    public function test_sending_the_same_file_again_to_the_same_slot_creates_nothing(): void
    {
        $session = ReconciliationSession::factory()->create();
        $upload = $this->paymentsCsv($this->paymentRows(2));

        $this->submit($session, ImportSlot::PaymentsSocial, $upload);
        $again = $this->submit($session, ImportSlot::PaymentsSocial, $upload);

        $this->assertNull($again);
        $this->assertSame(1, ImportFile::query()->count());
        $this->assertSame(2, PaymentEntry::query()->count());
    }

    public function test_same_file_in_the_other_payment_slot_is_refused(): void
    {
        $session = ReconciliationSession::factory()->create();
        $upload = $this->paymentsCsv($this->paymentRows(2));

        $this->submit($session, ImportSlot::PaymentsSocial, $upload);

        try {
            $this->submit($session, ImportSlot::PaymentsSaude, $upload);
            $this->fail('The upload should have been refused.');
        } catch (ActionRefusedException $exception) {
            $this->assertSame(__('conciliation.errors.same_file_other_slot'), $exception->getMessage());
        }

        $this->assertSame(1, ImportFile::query()->count());
    }

    public function test_only_the_first_sheet_is_read_and_the_sheet_count_is_kept(): void
    {
        $session = ReconciliationSession::factory()->create();
        $layout = app(PaymentsLayout::class);

        $upload = $this->xlsxFile($layout->headers(), $this->paymentRows(2), 'abas.xlsx', [[['outra aba'], ['lixo']]]);

        $this->submit($session, ImportSlot::PaymentsSocial, $upload);

        $file = $session->activeFiles()->sole();

        $this->assertSame(2, $file->sheet_count);
        $this->assertSame(2, $file->rows_imported);
    }

    public function test_missing_optional_layout_column_is_accepted_and_recorded(): void
    {
        $session = ReconciliationSession::factory()->create();
        $headers = array_values(array_diff(app(PaymentsLayout::class)->headers(), ['DS_AUDIT', 'ESPECIE']));

        $this->submit($session, ImportSlot::PaymentsSocial, $this->csvFile($headers, $this->paymentRows(2)));

        $file = $session->activeFiles()->sole();

        $this->assertEqualsCanonicalizing(['DS_AUDIT', 'ESPECIE'], $file->missing_columns);
        $this->assertNull(PaymentEntry::query()->firstOrFail()->species);
    }

    public function test_columns_in_any_order_and_extra_columns_are_accepted(): void
    {
        $session = ReconciliationSession::factory()->create();
        $headers = [...array_reverse(app(PaymentsLayout::class)->headers()), 'COLUNA_EXTRA'];

        $this->submit($session, ImportSlot::PaymentsSocial, $this->csvFile($headers, $this->paymentRows(2, ['COLUNA_EXTRA' => 'x'])));

        $file = $session->activeFiles()->sole();

        $this->assertSame(2, $file->rows_imported);
        $this->assertNull($file->missing_columns);
    }

    public function test_blank_rows_are_ignored(): void
    {
        $session = ReconciliationSession::factory()->create();
        $headers = app(PaymentsLayout::class)->headers();

        $this->submit($session, ImportSlot::PaymentsSocial, $this->csvFile($headers, [$this->paymentRow(), [], $this->paymentRow(), []]));

        $this->assertSame(2, $session->activeFiles()->sole()->rows_imported);
    }

    public function test_upload_is_refused_when_the_session_is_not_open(): void
    {
        $session = ReconciliationSession::factory()->processed()->create();

        $this->expectException(ActionRefusedException::class);

        $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(1)));
    }

    public function test_any_role_can_upload_to_a_session_created_by_someone_else(): void
    {
        $session = ReconciliationSession::factory()->create();
        $otherOperator = User::factory()->create();

        $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(1)), $otherOperator);

        $this->assertSame($otherOperator->id, $session->activeFiles()->sole()->uploaded_by);
    }
}
