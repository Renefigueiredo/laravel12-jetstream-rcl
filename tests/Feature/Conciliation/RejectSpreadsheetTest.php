<?php

namespace Tests\Feature\Conciliation;

use App\Actions\Conciliation\ActionRefusedException;
use App\Contracts\SpreadsheetReader;
use App\Enums\ImportAttemptStatus;
use App\Enums\ImportSlot;
use App\Livewire\Sessions\Show;
use App\Models\ImportFile;
use App\Models\PaymentEntry;
use App\Models\ReconciliationSession;
use App\Models\User;
use App\Services\Import\Layouts\AuthorizationsLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\SubmitsSpreadsheets;
use Tests\TestCase;

class RejectSpreadsheetTest extends TestCase
{
    use RefreshDatabase;
    use SubmitsSpreadsheets;

    /**
     * @return list<array<string, mixed>>
     */
    protected function rowsWithLettersInLine45(): array
    {
        $rows = $this->paymentRows(50);
        $rows[43]['VL_RECEBIDO'] = 'abc';

        return $rows;
    }

    public function test_letters_in_a_value_refuse_the_whole_file(): void
    {
        $session = ReconciliationSession::factory()->create();

        $attempt = $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->rowsWithLettersInLine45()));

        $this->assertSame(ImportAttemptStatus::Rejected, $attempt->status);
        $this->assertSame(1, $attempt->error_count);
        $this->assertSame([
            'row' => 45,
            'column' => 'VL_RECEBIDO',
            'value' => 'abc',
            'reason' => __('conciliation.errors.money.format'),
        ], $attempt->first_errors[0]);
        $this->assertSame(0, ImportFile::query()->count());
        $this->assertSame(0, PaymentEntry::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('conciliation/sessions'));
        $this->assertSame([], Storage::disk('local')->allFiles('conciliation/incoming'));
    }

    public function test_summary_shows_the_total_and_only_the_first_ten_errors(): void
    {
        $session = ReconciliationSession::factory()->create();
        $rows = $this->paymentRows(12, ['CEDENTE' => '']);

        $attempt = $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($rows));

        $this->assertSame(12, $attempt->error_count);
        $this->assertCount(10, $attempt->first_errors);
        $this->assertSame([2, 3, 4, 5, 6, 7, 8, 9, 10, 11], array_column($attempt->first_errors, 'row'));

        Livewire::actingAs($session->creator)
            ->test(Show::class, ['session' => $session])
            ->assertSee(__('conciliation.import.rejected'))
            ->assertSee(trans_choice('conciliation.import.error_total', 12, ['count' => 12]))
            ->assertSee(__('conciliation.errors.required'))
            ->assertSee(__('conciliation.import.download_errors'))
            ->assertSee(__('conciliation.slots.status.pending'))
            ->assertDontSee(__('conciliation.slots.status.loaded'));
    }

    public function test_full_error_report_lists_every_error_in_file_order(): void
    {
        $session = ReconciliationSession::factory()->create();
        $rows = $this->paymentRows(12, ['DT_LIQUIDACAO' => '2026-05-31']);

        $attempt = $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($rows, 'saude.csv'));

        $response = $this->actingAs($session->creator)->get(route('sessions.attempts.errors', $attempt));

        $response->assertOk()->assertDownload('erros-saude.xlsx');

        $report = iterator_to_array(app(SpreadsheetReader::class)->rows($this->pathOf($this->rawFile($response->streamedContent(), 'relatorio.xlsx'))));

        $this->assertSame(['Linha', 'Coluna', 'Valor encontrado', 'Motivo'], $report[1]);
        $this->assertCount(13, $report);
        $this->assertSame([2, 'DT_LIQUIDACAO', '2026-05-31', __('conciliation.errors.date.format')], $report[2]);
        $this->assertSame(13, $report[13][0]);
    }

    public function test_error_report_requires_login(): void
    {
        $session = ReconciliationSession::factory()->create();
        $attempt = $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->rowsWithLettersInLine45()));

        $this->get(route('sessions.attempts.errors', $attempt))->assertRedirect(route('login'));
    }

    public function test_attempt_without_report_is_not_found(): void
    {
        $session = ReconciliationSession::factory()->create();
        $attempt = $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(1)));

        $this->actingAs($session->creator)->get(route('sessions.attempts.errors', $attempt))->assertNotFound();
    }

    public function test_invalid_upload_keeps_the_previous_file_of_the_slot(): void
    {
        $session = ReconciliationSession::factory()->create();
        $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(3), 'valido.csv'));

        $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->rowsWithLettersInLine45(), 'invalido.csv'));

        $file = $session->activeFiles()->sole();

        $this->assertSame('valido.csv', $file->original_name);
        $this->assertSame(3, PaymentEntry::query()->where('import_file_id', $file->id)->count());
        $this->assertSame(1, ImportFile::query()->count());
    }

    public function test_file_with_only_the_header_is_refused(): void
    {
        $session = ReconciliationSession::factory()->create();

        $attempt = $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv([]));

        $this->assertSame(ImportAttemptStatus::Rejected, $attempt->status);
        $this->assertSame(__('conciliation.errors.no_entries'), $attempt->message);
    }

    public function test_empty_file_is_refused(): void
    {
        $session = ReconciliationSession::factory()->create();

        $attempt = $this->submit($session, ImportSlot::PaymentsSocial, $this->rawFile('', 'vazio.csv'));

        $this->assertSame(ImportAttemptStatus::Rejected, $attempt->status);
        $this->assertSame(__('conciliation.errors.no_entries'), $attempt->message);
    }

    public function test_file_whose_rows_are_all_skipped_by_value_is_refused(): void
    {
        $session = ReconciliationSession::factory()->create();

        $attempt = $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->paymentRows(3, ['VL_RECEBIDO' => '0'])));

        $this->assertSame(ImportAttemptStatus::Rejected, $attempt->status);
        $this->assertSame(__('conciliation.errors.no_valid_entries'), $attempt->message);
        $this->assertSame(0, ImportFile::query()->count());
    }

    public function test_file_type_that_is_not_accepted_is_refused_with_the_accepted_types(): void
    {
        $session = ReconciliationSession::factory()->create();

        try {
            $this->submit($session, ImportSlot::PaymentsSocial, $this->rawFile('conteudo', 'planilha.pdf'));
            $this->fail('The upload should have been refused.');
        } catch (ActionRefusedException $exception) {
            $this->assertSame(__('conciliation.errors.file_type'), $exception->getMessage());
            $this->assertStringContainsString('.xlsx', $exception->getMessage());
        }
    }

    public function test_file_above_the_size_limit_is_refused_with_the_limit(): void
    {
        config(['conciliation.upload.max_size_mb' => 1]);
        $session = ReconciliationSession::factory()->create();

        try {
            $this->submit($session, ImportSlot::PaymentsSocial, $this->rawFile(str_repeat('a', 1024 * 1024 + 1), 'grande.csv'));
            $this->fail('The upload should have been refused.');
        } catch (ActionRefusedException $exception) {
            $this->assertSame(__('conciliation.errors.file_size', ['max' => 1]), $exception->getMessage());
        }

        $this->assertSame([], Storage::disk('local')->allFiles('conciliation'));
    }

    public function test_payments_file_in_the_authorizations_slot_lists_the_missing_columns(): void
    {
        $session = ReconciliationSession::factory()->create();

        $attempt = $this->submit($session, ImportSlot::Authorizations, $this->paymentsCsv($this->paymentRows(2)));

        $this->assertSame(ImportAttemptStatus::Rejected, $attempt->status);
        $this->assertSame(0, $attempt->error_count);

        foreach (app(AuthorizationsLayout::class)->requiredHeaders() as $header) {
            $this->assertStringContainsString($header, $attempt->message);
        }

        Livewire::actingAs($session->creator)
            ->test(Show::class, ['session' => $session])
            ->assertSee($attempt->message);
    }

    public function test_required_header_is_recognized_despite_case_accents_and_spaces(): void
    {
        $session = ReconciliationSession::factory()->create();
        $headers = array_map(
            fn (string $header): string => $header === 'MAP_SOLICITACAO' ? ' map_solicitação ' : $header,
            app(AuthorizationsLayout::class)->headers(),
        );
        $rows = array_map(function (array $row): array {
            $row[' map_solicitação '] = $row['MAP_SOLICITACAO'];

            return $row;
        }, $this->authorizationRows(2));

        $attempt = $this->submit($session, ImportSlot::Authorizations, $this->xlsxFile($headers, $rows));

        $this->assertSame(ImportAttemptStatus::Accepted, $attempt->status);
    }

    public function test_errors_point_to_the_right_row_and_column(): void
    {
        $session = ReconciliationSession::factory()->create();
        $rows = [
            $this->paymentRow(),
            $this->paymentRow(['DT_LIQUIDACAO' => '05/31/2026']),
            $this->paymentRow(['OBRIGACAO' => '']),
            $this->paymentRow(['VL_RECEBIDO' => '1,2345']),
        ];

        $attempt = $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($rows));

        $this->assertSame([
            ['row' => 3, 'column' => 'DT_LIQUIDACAO', 'value' => '05/31/2026', 'reason' => __('conciliation.errors.date.invalid')],
            ['row' => 4, 'column' => 'OBRIGACAO', 'value' => '', 'reason' => __('conciliation.errors.required')],
            ['row' => 5, 'column' => 'VL_RECEBIDO', 'value' => '1,2345', 'reason' => __('conciliation.errors.money.decimals')],
        ], $attempt->first_errors);
    }

    public function test_corrupted_xlsx_is_refused_as_unreadable(): void
    {
        $session = ReconciliationSession::factory()->create();

        $attempt = $this->submit($session, ImportSlot::Authorizations, $this->rawFile('isto nao e um xlsx', 'quebrado.xlsx'));

        $this->assertSame(ImportAttemptStatus::Rejected, $attempt->status);
        $this->assertSame(__('conciliation.errors.unreadable'), $attempt->message);
    }

    public function test_any_authenticated_user_can_download_the_report_of_any_session(): void
    {
        $session = ReconciliationSession::factory()->create();
        $attempt = $this->submit($session, ImportSlot::PaymentsSocial, $this->paymentsCsv($this->rowsWithLettersInLine45()));

        $this->actingAs(User::factory()->create())->get(route('sessions.attempts.errors', $attempt))->assertOk();
    }
}
