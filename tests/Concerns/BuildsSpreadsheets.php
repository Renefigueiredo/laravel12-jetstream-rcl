<?php

namespace Tests\Concerns;

use App\Services\Import\Layouts\AuthorizationsLayout;
use App\Services\Import\Layouts\PaymentsLayout;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

trait BuildsSpreadsheets
{
    protected ?string $spreadsheetDirectory = null;

    protected ?string $lastSpreadsheetPath = null;

    protected function tearDownBuildsSpreadsheets(): void
    {
        if ($this->spreadsheetDirectory !== null) {
            File::deleteDirectory($this->spreadsheetDirectory);
            $this->spreadsheetDirectory = null;
        }
    }

    /**
     * A valid row of the payments layout, keyed by header.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function paymentRow(array $overrides = []): array
    {
        static $sequence = 0;
        $sequence++;

        return [
            ...app(PaymentsLayout::class)->exampleRow(),
            'OBRIGACAO' => (string) (900000 + $sequence),
            'CD_MOVIMENTO_CONTA' => (string) (700000 + $sequence),
            ...$overrides,
        ];
    }

    /**
     * A valid row of the authorizations layout, keyed by header.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function authorizationRow(array $overrides = []): array
    {
        static $sequence = 0;
        $sequence++;

        return [
            ...app(AuthorizationsLayout::class)->exampleRow(),
            'MAP_SOLICITACAO' => 'MATERIAL DE ESCRITORIO - '.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT),
            ...$overrides,
        ];
    }

    /**
     * @param  int  $count  Number of valid rows
     * @param  array<string, mixed>  $overrides
     * @return list<array<string, mixed>>
     */
    protected function paymentRows(int $count, array $overrides = []): array
    {
        return array_map(fn (): array => $this->paymentRow($overrides), range(1, $count));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return list<array<string, mixed>>
     */
    protected function authorizationRows(int $count, array $overrides = []): array
    {
        return array_map(fn (): array => $this->authorizationRow($overrides), range(1, $count));
    }

    /**
     * Build a payments file as the ERP exports it: semicolon-separated .csv.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    protected function paymentsCsv(array $rows, string $name = 'pagamentos.csv', string $encoding = 'UTF-8'): UploadedFile
    {
        return $this->csvFile(app(PaymentsLayout::class)->headers(), $rows, $name, ';', $encoding);
    }

    /**
     * Build an authorizations file as ELO exports it: .xlsx.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    protected function authorizationsXlsx(array $rows, string $name = 'autorizacoes.xlsx'): UploadedFile
    {
        return $this->xlsxFile(app(AuthorizationsLayout::class)->headers(), $rows, $name);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, mixed>>  $rows  Rows keyed by header; missing headers become empty cells
     * @param  list<list<list<mixed>>>  $extraSheets  Additional sheets, each a list of rows
     */
    protected function xlsxFile(array $headers, array $rows, string $name = 'planilha.xlsx', array $extraSheets = []): UploadedFile
    {
        $path = $this->spreadsheetPath($name);

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers));

        $dateStyle = (new Style)->setFormat('dd/mm/yyyy');

        foreach ($rows as $row) {
            $writer->addRow(new Row(array_map(
                fn (mixed $cell): Cell => Cell::fromValue($cell, $cell instanceof DateTimeInterface ? $dateStyle : null),
                $this->orderedCells($headers, $row),
            )));
        }

        foreach ($extraSheets as $sheetRows) {
            $writer->addNewSheetAndMakeItCurrent();

            foreach ($sheetRows as $sheetRow) {
                $writer->addRow(Row::fromValues($sheetRow));
            }
        }

        $writer->close();

        return $this->uploadedFile($path, $name);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, mixed>>  $rows
     */
    protected function csvFile(array $headers, array $rows, string $name = 'planilha.csv', string $delimiter = ';', string $encoding = 'UTF-8', bool $withBom = false): UploadedFile
    {
        $lines = [implode($delimiter, $headers)];

        foreach ($rows as $row) {
            $lines[] = implode($delimiter, array_map(
                fn (mixed $cell): string => str_contains((string) $cell, $delimiter) ? '"'.$cell.'"' : (string) $cell,
                $this->orderedCells($headers, $row),
            ));
        }

        $content = implode("\r\n", $lines)."\r\n";

        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, $encoding, 'UTF-8');
        } elseif ($withBom) {
            $content = "\u{FEFF}".$content;
        }

        return $this->rawFile($content, $name);
    }

    protected function rawFile(string $content, string $name): UploadedFile
    {
        $path = $this->spreadsheetPath($name);

        file_put_contents($path, $content);

        return $this->uploadedFile($path, $name);
    }

    /**
     * Wrap a built file as an upload; its path on disk stays in $lastSpreadsheetPath.
     */
    protected function uploadedFile(string $path, string $name): UploadedFile
    {
        $this->lastSpreadsheetPath = $path;

        return UploadedFile::fake()->createWithContent($name, (string) file_get_contents($path));
    }

    /**
     * The path on disk of the file that was just built.
     */
    protected function pathOf(UploadedFile $file): string
    {
        return (string) $this->lastSpreadsheetPath;
    }

    /**
     * @param  list<string>  $headers
     * @param  array<string, mixed>  $row
     * @return list<mixed>
     */
    protected function orderedCells(array $headers, array $row): array
    {
        return array_map(fn (string $header): mixed => $row[$header] ?? '', $headers);
    }

    protected function spreadsheetPath(string $name): string
    {
        $this->spreadsheetDirectory ??= sys_get_temp_dir().'/conciliation-tests-'.bin2hex(random_bytes(6));

        $directory = $this->spreadsheetDirectory.'/'.bin2hex(random_bytes(4));

        File::ensureDirectoryExists($directory);

        return $directory.'/'.$name;
    }
}
