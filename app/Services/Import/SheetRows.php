<?php

namespace App\Services\Import;

use App\Contracts\SpreadsheetLayout;
use App\Contracts\SpreadsheetReader;
use App\Enums\ImportSlot;
use Generator;

class SheetRows
{
    public function __construct(protected SpreadsheetReader $reader, protected HeaderNormalizer $headerNormalizer) {}

    /**
     * Read the header row as a map of normalized header to column index and original text.
     *
     * @return array<string, array{index: int, name: string}>|null Null when the file has no rows
     */
    public function headers(string $absolutePath): ?array
    {
        foreach ($this->reader->rows($absolutePath) as $cells) {
            return $this->mapHeaders($cells);
        }

        return null;
    }

    /**
     * Interpret every data row with the layout, skipping blank rows.
     *
     * Reading stops after a long run of consecutive blank rows: spreadsheets exported with a
     * formula or a format dragged to the last row of the sheet have a million empty rows.
     *
     * @return Generator<int, ParsedRow|list<RowError>|null>
     */
    public function parsed(string $absolutePath, SpreadsheetLayout $layout, ImportSlot $slot): Generator
    {
        $headers = null;
        $blankRowsLimit = max(1, (int) config('conciliation.upload.blank_rows_limit'));
        $consecutiveBlankRows = 0;

        foreach ($this->reader->rows($absolutePath) as $rowNumber => $cells) {
            if ($headers === null) {
                $headers = $this->mapHeaders($cells);

                continue;
            }

            if ($this->isBlank($cells)) {
                if (++$consecutiveBlankRows >= $blankRowsLimit) {
                    return;
                }

                continue;
            }

            $consecutiveBlankRows = 0;

            $row = [];
            $raw = [];

            foreach ($headers as $normalized => $header) {
                $cell = $cells[$header['index']] ?? null;
                $row[$normalized] = $cell;
                $raw[$header['name']] = CellText::from($cell);
            }

            yield $rowNumber => $layout->parse($row, $raw, $rowNumber, $slot);
        }
    }

    /**
     * @param  array<int, mixed>  $cells
     * @return array<string, array{index: int, name: string}>
     */
    protected function mapHeaders(array $cells): array
    {
        $headers = [];

        foreach (array_values($cells) as $index => $cell) {
            $normalized = $this->headerNormalizer->normalize($cell);

            if ($normalized !== '' && ! isset($headers[$normalized])) {
                $headers[$normalized] = ['index' => $index, 'name' => trim((string) $cell)];
            }
        }

        return $headers;
    }

    /**
     * @param  array<int, mixed>  $cells
     */
    protected function isBlank(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (CellText::from($cell) !== null) {
                return false;
            }
        }

        return true;
    }
}
