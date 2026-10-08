<?php

namespace Tests\Feature\Conciliation;

use App\Contracts\SpreadsheetReader;
use App\Enums\ExcludedCodeImportStatus;
use App\Enums\UserPermission;
use App\Models\ExcludedOperationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ImportsExcludedCodes;
use Tests\TestCase;

class ExcludedCodeTemplateTest extends TestCase
{
    use ImportsExcludedCodes;
    use RefreshDatabase;

    public function test_template_is_an_xlsx_with_headers_and_an_example_row(): void
    {
        $response = $this->actingAs(User::factory()->administrador()->create())->get(route('excluded-codes.template'));

        $response->assertOk()->assertDownload('modelo-codigos-excluidos.xlsx');

        $rows = iterator_to_array(app(SpreadsheetReader::class)->rows($this->pathOf($this->rawFile($response->streamedContent(), 'modelo.xlsx'))));

        $this->assertSame(['COD_OPERACAO', 'DESCRICAO'], $rows[1]);
        $this->assertCount(2, $rows);
        $this->assertSame('20150652', $rows[2][0]);
        $this->assertSame(__('conciliation.excluded_codes.import.template_description'), $rows[2][1]);
    }

    public function test_template_itself_can_be_imported(): void
    {
        $administrator = User::factory()->administrador()->create();
        $response = $this->actingAs($administrator)->get(route('excluded-codes.template'));

        $import = $this->importCodes($administrator, $this->rawFile($response->streamedContent(), 'modelo-codigos-excluidos.xlsx'));

        $this->assertSame(ExcludedCodeImportStatus::Completed, $import->status);
        $this->assertSame('20150652', ExcludedOperationCode::query()->sole()->code);
    }

    public function test_template_requires_the_permission(): void
    {
        $this->get(route('excluded-codes.template'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())->get(route('excluded-codes.template'))->assertForbidden();

        $this->actingAs(User::factory()->withPermission(UserPermission::ManageExcludedCodes)->create())
            ->get(route('excluded-codes.template'))
            ->assertOk();
    }
}
