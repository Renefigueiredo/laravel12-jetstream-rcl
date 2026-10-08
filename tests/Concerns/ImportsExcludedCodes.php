<?php

namespace Tests\Concerns;

use App\Actions\Conciliation\SubmitExcludedCodeImport;
use App\Models\ExcludedCodeImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait ImportsExcludedCodes
{
    use BuildsSpreadsheets;

    protected function setUpImportsExcludedCodes(): void
    {
        Storage::fake('local');
    }

    /**
     * Build a codes file from [code, description] pairs.
     *
     * @param  list<array{0: mixed, 1?: mixed}>  $pairs
     */
    protected function codesCsv(array $pairs, string $name = 'codigos.csv', string $delimiter = ';', bool $withHeader = true, string $encoding = 'UTF-8'): UploadedFile
    {
        $lines = $withHeader ? ['COD_OPERACAO'.$delimiter.'DESCRICAO'] : [];

        foreach ($pairs as $pair) {
            $lines[] = $pair[0].$delimiter.($pair[1] ?? '');
        }

        $content = implode("\r\n", $lines)."\r\n";

        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, $encoding, 'UTF-8');
        }

        return $this->rawFile($content, $name);
    }

    /**
     * @param  list<array{0: mixed, 1?: mixed}>  $pairs
     * @param  list<list<list<mixed>>>  $extraSheets
     */
    protected function codesXlsx(array $pairs, string $name = 'codigos.xlsx', array $extraSheets = []): UploadedFile
    {
        return $this->xlsxFile(
            ['COD_OPERACAO', 'DESCRICAO'],
            array_map(fn (array $pair): array => ['COD_OPERACAO' => $pair[0], 'DESCRICAO' => $pair[1] ?? ''], $pairs),
            $name,
            $extraSheets,
        );
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    protected function codePairs(int $count, int $start = 30000000): array
    {
        return array_map(fn (int $index): array => [(string) ($start + $index), 'Operação '.$index], range(1, $count));
    }

    /**
     * Submit a codes file; the queue runs synchronously in tests.
     */
    protected function importCodes(User $user, UploadedFile $file): ExcludedCodeImport
    {
        return app(SubmitExcludedCodeImport::class)->handle($user, $file)->fresh();
    }
}
