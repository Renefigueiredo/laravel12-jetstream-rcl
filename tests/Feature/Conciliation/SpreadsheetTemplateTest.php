<?php

namespace Tests\Feature\Conciliation;

use App\Contracts\SpreadsheetReader;
use App\Models\User;
use App\Services\Import\Layouts\AuthorizationsLayout;
use App\Services\Import\Layouts\PaymentsLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSpreadsheets;
use Tests\TestCase;

class SpreadsheetTemplateTest extends TestCase
{
    use BuildsSpreadsheets;
    use RefreshDatabase;

    public function test_authorizations_template_is_an_xlsx_with_headers_and_an_example_row(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('templates.download', 'autorizacoes'));

        $response->assertOk()->assertDownload('modelo-autorizacoes.xlsx');

        $rows = iterator_to_array(app(SpreadsheetReader::class)->rows($this->pathOf($this->rawFile($response->streamedContent(), 'modelo.xlsx'))));

        $this->assertSame(app(AuthorizationsLayout::class)->headers(), $rows[1]);
        $this->assertCount(2, $rows);
        $this->assertSame('FORNECEDOR EXEMPLO LTDA', $rows[2][5]);
    }

    public function test_payments_template_is_a_semicolon_csv_with_headers_and_an_example_row(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('templates.download', 'pagamentos'));

        $response->assertOk()->assertDownload('modelo-pagamentos.csv');

        $lines = preg_split('/\r\n|\n/', trim(str_replace("\u{FEFF}", '', $response->streamedContent())));

        $this->assertSame(implode(';', app(PaymentsLayout::class)->headers()), $lines[0]);
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('FORNECEDOR EXEMPLO LTDA', $lines[1]);
    }

    public function test_unknown_layout_is_not_found(): void
    {
        $this->actingAs(User::factory()->create())->get(route('templates.download', 'outro'))->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('templates.download', 'pagamentos'))->assertRedirect(route('login'));
    }
}
