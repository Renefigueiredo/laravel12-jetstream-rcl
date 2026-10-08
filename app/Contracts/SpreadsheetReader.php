<?php

namespace App\Contracts;

interface SpreadsheetReader
{
    /**
     * Read the rows of the first sheet, keyed by their row number in the file (starting at 1).
     *
     * Cells are returned as they are stored: text in UTF-8, numbers and dates by value.
     *
     * @return iterable<int, array<int, mixed>>
     */
    public function rows(string $absolutePath): iterable;

    public function sheetCount(string $absolutePath): int;
}
