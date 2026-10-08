<?php

namespace Tests\Feature\Conciliation;

use App\Actions\Conciliation\ActionRefusedException;
use App\Actions\Conciliation\SubmitExcludedCodeImport;
use App\Enums\AuditAction;
use App\Enums\ExcludedCodeImportStatus;
use App\Enums\ExcludedCodeSource;
use App\Livewire\ExcludedCodes\Index;
use App\Models\AuditLog;
use App\Models\ExcludedCodeImport;
use App\Models\ExcludedOperationCode;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\ImportsExcludedCodes;
use Tests\TestCase;

class ImportExcludedCodesTest extends TestCase
{
    use ImportsExcludedCodes;
    use RefreshDatabase;

    protected User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->administrador()->create();
    }

    public function test_semicolon_csv_is_imported(): void
    {
        $this->travelTo('2026-07-10 17:20:30');

        $import = $this->importCodes($this->administrator, $this->codesCsv([['20150652', 'Folha de pagamento'], ['11018953', '']]));

        $this->assertSame(ExcludedCodeImportStatus::Completed, $import->status);
        $this->assertSame(2, $import->added_count);
        $this->assertSame(0, $import->ignored_count);
        $this->assertNotNull($import->finished_at);

        $code = ExcludedOperationCode::query()->where('code', '20150652')->sole();

        $this->assertSame('Folha de pagamento', $code->description);
        $this->assertSame(ExcludedCodeSource::File, $code->source);
        $this->assertTrue($code->import->is($import));
        $this->assertTrue($code->creator->is($this->administrator));
        $this->assertSame('2026-07-10 17:20:30', $code->created_at->format('Y-m-d H:i:s'));
        $this->assertNull(ExcludedOperationCode::query()->where('code', '11018953')->sole()->description);
    }

    public function test_comma_csv_is_imported(): void
    {
        $import = $this->importCodes($this->administrator, $this->codesCsv([['20150652', 'Folha'], ['11018953', 'Convênio']], delimiter: ','));

        $this->assertSame(ExcludedCodeImportStatus::Completed, $import->status);
        $this->assertSame(['11018953', '20150652'], ExcludedOperationCode::query()->orderBy('code')->pluck('code')->all());
        $this->assertSame('Convênio', ExcludedOperationCode::query()->where('code', '11018953')->value('description'));
    }

    public function test_csv_without_header_is_imported(): void
    {
        $import = $this->importCodes($this->administrator, $this->codesCsv([['20150652', 'Folha'], ['11018953', 'Convênio']], withHeader: false));

        $this->assertSame(2, $import->added_count);
    }

    public function test_xlsx_is_imported_reading_only_the_first_sheet(): void
    {
        $upload = $this->codesXlsx([[20150652, 'Folha de pagamento'], ['00123', 'Zeros à esquerda']], extraSheets: [[['99999999', 'Outra aba']]]);

        $import = $this->importCodes($this->administrator, $upload);

        $this->assertSame(ExcludedCodeImportStatus::Completed, $import->status);
        $this->assertSame(['00123', '20150652'], ExcludedOperationCode::query()->orderBy('code')->pluck('code')->all());
    }

    public function test_windows_1252_csv_keeps_accented_descriptions(): void
    {
        $this->importCodes($this->administrator, $this->codesCsv([['11018953', 'Convênio de reciprocidade - ação']], encoding: 'Windows-1252'));

        $this->assertSame('Convênio de reciprocidade - ação', ExcludedOperationCode::query()->sole()->description);
    }

    public function test_existing_codes_are_ignored_and_the_summary_is_shown(): void
    {
        $existing = $this->codePairs(5, 20000000);

        foreach ($existing as [$code]) {
            ExcludedOperationCode::factory()->create(['code' => $code, 'description' => 'Original']);
        }

        $upload = $this->codesCsv([...$this->codePairs(10), ...$existing]);

        Livewire::actingAs($this->administrator)
            ->test(Index::class)
            ->set('upload', $upload)
            ->assertHasNoErrors()
            ->assertSee('Importação concluída: 10 códigos adicionados e 5 códigos ignorados por já estarem cadastrados ou repetidos no arquivo.');

        $import = ExcludedCodeImport::query()->sole();

        $this->assertSame(ExcludedCodeImportStatus::Completed, $import->status);
        $this->assertSame(10, $import->added_count);
        $this->assertSame(5, $import->ignored_count);
        $this->assertSame(15, ExcludedOperationCode::query()->count());
        $this->assertSame(5, ExcludedOperationCode::query()->where('description', 'Original')->count());
        $this->assertSame(10, $import->codes()->count());
    }

    public function test_codes_repeated_in_the_file_count_as_ignored(): void
    {
        $import = $this->importCodes($this->administrator, $this->codesCsv([['20150652', 'Primeira'], ['20150652', 'Segunda'], ['20150652', 'Terceira']]));

        $this->assertSame(1, $import->added_count);
        $this->assertSame(2, $import->ignored_count);
        $this->assertSame('Primeira', ExcludedOperationCode::query()->sole()->description);
    }

    public function test_file_with_only_existing_codes_completes_with_nothing_added(): void
    {
        ExcludedOperationCode::factory()->create(['code' => '20150652']);

        $import = $this->importCodes($this->administrator, $this->codesCsv([['20150652', 'Folha']]));

        $this->assertSame(ExcludedCodeImportStatus::Completed, $import->status);
        $this->assertSame(0, $import->added_count);
        $this->assertSame(1, $import->ignored_count);
        $this->assertSame([], AuditLog::query()->sole()->after['codes']);
    }

    public function test_one_invalid_row_refuses_the_whole_file(): void
    {
        $pairs = $this->codePairs(99);
        array_splice($pairs, 43, 0, [['', 'Linha sem código']]);

        Livewire::actingAs($this->administrator)
            ->test(Index::class)
            ->set('upload', $this->codesCsv($pairs))
            ->assertSee(__('conciliation.excluded_codes.import.rejected'))
            ->assertSee(__('conciliation.excluded_codes.import.row_errors.code_blank'))
            ->assertSee('COD_OPERACAO');

        $import = ExcludedCodeImport::query()->sole();

        $this->assertSame(ExcludedCodeImportStatus::Rejected, $import->status);
        $this->assertSame([[
            'row' => 45,
            'column' => 'COD_OPERACAO',
            'reason' => __('conciliation.excluded_codes.import.row_errors.code_blank'),
        ]], $import->errors);
        $this->assertSame(0, ExcludedOperationCode::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_every_invalid_row_is_listed(): void
    {
        $import = $this->importCodes($this->administrator, $this->codesCsv([
            ['20150652', 'Válido'],
            ['12-34', 'Hífen'],
            ['', 'Sem código'],
            ['11018953', str_repeat('a', 256)],
        ]));

        $this->assertSame(ExcludedCodeImportStatus::Rejected, $import->status);
        $this->assertSame([3, 4, 5], array_column($import->errors, 'row'));
        $this->assertSame(['COD_OPERACAO', 'COD_OPERACAO', 'DESCRICAO'], array_column($import->errors, 'column'));
        $this->assertSame(0, ExcludedOperationCode::query()->count());
    }

    public function test_file_without_codes_is_refused(): void
    {
        $import = $this->importCodes($this->administrator, $this->codesCsv([]));

        $this->assertSame(ExcludedCodeImportStatus::Rejected, $import->status);
        $this->assertNull($import->errors);
        $this->assertSame(__('conciliation.excluded_codes.import.no_codes'), $import->failure_message);
    }

    public function test_file_above_the_row_limit_is_refused(): void
    {
        config(['conciliation.excluded_codes.max_rows' => 5]);

        $import = $this->importCodes($this->administrator, $this->codesCsv($this->codePairs(6)));

        $this->assertSame(ExcludedCodeImportStatus::Rejected, $import->status);
        $this->assertSame(__('conciliation.excluded_codes.import.too_many_rows', ['max' => '5']), $import->failure_message);
        $this->assertSame(0, ExcludedOperationCode::query()->count());
    }

    public function test_unreadable_file_is_refused(): void
    {
        $import = $this->importCodes($this->administrator, $this->rawFile('this is not a spreadsheet', 'codigos.xlsx'));

        $this->assertSame(ExcludedCodeImportStatus::Rejected, $import->status);
        $this->assertSame(__('conciliation.errors.unreadable'), $import->failure_message);
    }

    public function test_file_type_and_size_are_checked_before_queueing(): void
    {
        config(['conciliation.excluded_codes.max_size_mb' => 1]);

        $screen = Livewire::actingAs($this->administrator)->test(Index::class);

        $screen->set('upload', $this->rawFile("20150652\r\n", 'codigos.txt'))
            ->assertSee(__('conciliation.excluded_codes.import.file_type'));

        $screen->set('upload', $this->rawFile(str_repeat("20150652;Folha de pagamento\r\n", 45000), 'grande.csv'))
            ->assertSee(__('conciliation.errors.file_size', ['max' => 1]));

        $this->assertSame(0, ExcludedCodeImport::query()->count());
        $this->assertSame(0, ExcludedOperationCode::query()->count());
    }

    public function test_second_upload_is_refused_while_one_is_in_progress(): void
    {
        ExcludedCodeImport::factory()->processing()->create(['user_id' => $this->administrator->id]);

        try {
            app(SubmitExcludedCodeImport::class)->handle($this->administrator, $this->codesCsv($this->codePairs(1)));
            $this->fail('The second upload should have been refused.');
        } catch (ActionRefusedException $exception) {
            $this->assertSame(__('conciliation.excluded_codes.import.already_running'), $exception->getMessage());
        }

        $this->assertSame(1, ExcludedCodeImport::query()->count());
    }

    public function test_another_user_can_upload_while_one_is_in_progress(): void
    {
        ExcludedCodeImport::factory()->processing()->create();

        $import = $this->importCodes($this->administrator, $this->codesCsv($this->codePairs(1)));

        $this->assertSame(ExcludedCodeImportStatus::Completed, $import->status);
    }

    public function test_screen_shows_the_import_in_progress_and_blocks_a_new_upload(): void
    {
        ExcludedCodeImport::factory()->processing()->create(['user_id' => $this->administrator->id]);

        Livewire::actingAs($this->administrator)
            ->test(Index::class)
            ->assertSee(__('conciliation.excluded_codes.import.in_progress'))
            ->assertDontSeeHtml('id="excluded-codes-upload"');
    }

    public function test_completed_import_is_audited_with_the_codes_added(): void
    {
        ExcludedOperationCode::factory()->create(['code' => '11018953']);

        $import = $this->importCodes($this->administrator, $this->codesCsv([['20150652', 'Folha'], ['11018953', 'Já existe'], ['00123', '']], 'julho.csv'));

        $log = AuditLog::query()->sole();

        $this->assertSame(AuditAction::ExcludedCodesImported, $log->action);
        $this->assertSame('Inclusão por arquivo', $log->action->label());
        $this->assertTrue($log->user->is($this->administrator));
        $this->assertSame('excluded_code_import', $log->auditable_type);
        $this->assertSame($import->id, $log->auditable_id);
        $this->assertSame('julho.csv', $log->label);
        $this->assertNull($log->before);
        $this->assertSame(['file' => 'julho.csv', 'added' => 2, 'ignored' => 1, 'codes' => ['00123', '20150652']], $log->after);
    }

    public function test_uploaded_file_is_kept_unchanged_on_the_private_disk(): void
    {
        $upload = $this->codesCsv([['20150652', 'Folha']], 'julho.csv');
        $original = file_get_contents($this->pathOf($upload));

        $import = $this->importCodes($this->administrator, $upload);

        $this->assertSame('julho.csv', $import->original_name);
        $this->assertSame('local', $import->disk);
        $this->assertSame(strlen($original), $import->size_bytes);
        $this->assertSame($original, Storage::disk('local')->get($import->path));
    }

    public function test_user_without_the_permission_cannot_import(): void
    {
        $this->expectException(AuthorizationException::class);

        app(SubmitExcludedCodeImport::class)->handle(User::factory()->create(), $this->codesCsv($this->codePairs(1)));
    }
}
