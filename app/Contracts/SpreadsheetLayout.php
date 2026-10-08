<?php

namespace App\Contracts;

use App\Enums\ImportSlot;
use App\Enums\SpreadsheetLayoutType;
use App\Services\Import\ParsedRow;
use App\Services\Import\RowError;

interface SpreadsheetLayout
{
    public function type(): SpreadsheetLayoutType;

    /**
     * Every header of the layout, as exported by the source system.
     *
     * @return list<string>
     */
    public function headers(): array;

    /**
     * Headers without which the file is refused.
     *
     * @return list<string>
     */
    public function requiredHeaders(): array;

    /**
     * Column whose value decides whether the row is skipped (zero or negative).
     */
    public function amountColumn(): string;

    /**
     * Column whose date is compared with the period of the session.
     */
    public function periodDateColumn(): string;

    /**
     * A fictitious row used in the template spreadsheet, keyed by header.
     *
     * @return array<string, string>
     */
    public function exampleRow(): array;

    /**
     * Interpret one data row.
     *
     * Returns null when the row is skipped because its amount is zero or negative.
     *
     * @param  array<string, mixed>  $row  Cells keyed by normalized header
     * @param  array<string, string|null>  $raw  Cells keyed by the header as written in the file
     * @return ParsedRow|list<RowError>|null
     */
    public function parse(array $row, array $raw, int $rowNumber, ImportSlot $slot): ParsedRow|array|null;
}
