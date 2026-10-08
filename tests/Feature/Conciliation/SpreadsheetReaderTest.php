<?php

namespace Tests\Feature\Conciliation;

use App\Contracts\SpreadsheetReader;
use App\Services\Import\OpenSpoutSpreadsheetReader;
use DateTimeImmutable;
use DateTimeInterface;
use Tests\Concerns\BuildsSpreadsheets;
use Tests\TestCase;
use ZipArchive;

class SpreadsheetReaderTest extends TestCase
{
    use BuildsSpreadsheets;

    /**
     * Store calculated values next to the formulas of the first sheet, as Excel does when saving.
     *
     * @param  array<string, string>  $replacements
     */
    protected function setCalculatedValues(string $path, array $replacements): void
    {
        $zip = new ZipArchive;
        $zip->open($path);

        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');

        $this->assertStringContainsString(array_key_first($replacements), $sheet);

        $zip->addFromString('xl/worksheets/sheet1.xml', strtr($sheet, $replacements));
        $zip->close();
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function read(string $path): array
    {
        return iterator_to_array(app(SpreadsheetReader::class)->rows($path));
    }

    public function test_container_resolves_the_openspout_reader(): void
    {
        $this->assertInstanceOf(OpenSpoutSpreadsheetReader::class, app(SpreadsheetReader::class));
    }

    public function test_it_reads_xlsx_cells_by_value(): void
    {
        $file = $this->xlsxFile(['NOME', 'VALOR', 'DATA'], [
            ['NOME' => 'Padaria São João', 'VALOR' => 240.8, 'DATA' => new DateTimeImmutable('2026-07-27')],
        ]);

        $rows = $this->read($this->lastSpreadsheetPath);

        $this->assertSame(['NOME', 'VALOR', 'DATA'], $rows[1]);
        $this->assertSame('Padaria São João', $rows[2][0]);
        $this->assertSame(240.8, $rows[2][1]);
        $this->assertInstanceOf(DateTimeInterface::class, $rows[2][2]);
        $this->assertSame('2026-07-27', $rows[2][2]->format('Y-m-d'));
    }

    public function test_formula_cells_are_read_by_their_calculated_value(): void
    {
        $this->xlsxFile(['VALOR', 'VAZIO', 'SEM_CALCULO'], [['VALOR' => '=1+2', 'VAZIO' => '=A1', 'SEM_CALCULO' => '=3+4']]);

        $this->setCalculatedValues($this->lastSpreadsheetPath, [
            '<f>1+2</f>' => '<f>1+2</f><v>3895.73</v>',
            '<c r="B2" s="0"><f>A1</f>' => '<c r="B2" s="0" t="str"><f>A1</f><v></v>',
        ]);

        $rows = $this->read($this->lastSpreadsheetPath);

        $this->assertSame(3895.73, $rows[2][0]);
        $this->assertSame('', $rows[2][1]);
        $this->assertNotSame('=3+4', $rows[2][2]);
        $this->assertNotSame('3+4', $rows[2][2]);
    }

    public function test_it_reads_only_the_first_sheet_and_counts_the_sheets(): void
    {
        $file = $this->xlsxFile(['A'], [['A' => 'primeira']], 'abas.xlsx', [[['B'], ['segunda']]]);

        $rows = $this->read($this->lastSpreadsheetPath);

        $this->assertCount(2, $rows);
        $this->assertSame('primeira', $rows[2][0]);
        $this->assertSame(2, app(SpreadsheetReader::class)->sheetCount($this->lastSpreadsheetPath));
    }

    public function test_it_reads_semicolon_csv_in_windows_1252(): void
    {
        $file = $this->csvFile(['CEDENTE', 'VALOR'], [['CEDENTE' => 'Conceição Serviços', 'VALOR' => '12229.76']], 'erp.csv', ';', 'Windows-1252');

        $rows = $this->read($this->lastSpreadsheetPath);

        $this->assertSame(['CEDENTE', 'VALOR'], $rows[1]);
        $this->assertSame(['Conceição Serviços', '12229.76'], $rows[2]);
        $this->assertSame(1, app(SpreadsheetReader::class)->sheetCount($this->lastSpreadsheetPath));
    }

    public function test_it_reads_comma_csv_in_utf8_with_bom(): void
    {
        $file = $this->csvFile(['CEDENTE', 'VALOR'], [['CEDENTE' => 'Conceição', 'VALOR' => '10.5']], 'excel.csv', ',', 'UTF-8', true);

        $rows = $this->read($this->lastSpreadsheetPath);

        $this->assertSame(['CEDENTE', 'VALOR'], $rows[1]);
        $this->assertSame(['Conceição', '10.5'], $rows[2]);
    }

    public function test_row_numbers_match_the_file_when_there_are_blank_rows(): void
    {
        $file = $this->rawFile("A;B\r\n1;2\r\n\r\n3;4\r\n", 'brancos.csv');

        $rows = $this->read($this->lastSpreadsheetPath);

        $this->assertSame(['3', '4'], $rows[4]);
    }
}
