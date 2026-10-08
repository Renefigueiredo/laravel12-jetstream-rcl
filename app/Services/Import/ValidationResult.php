<?php

namespace App\Services\Import;

use Carbon\CarbonImmutable;

final class ValidationResult
{
    public int $rowsTotal = 0;

    public int $rowsValid = 0;

    public int $rowsSkippedValue = 0;

    public int $rowsOutOfPeriod = 0;

    public ?CarbonImmutable $minDate = null;

    public ?CarbonImmutable $maxDate = null;

    public int $errorCount = 0;

    /** @var list<array{row: int, column: string, value: string, reason: string}> */
    public array $firstErrors = [];

    /** @var list<string> */
    public array $missingColumns = [];

    public int $sheetCount = 1;

    /**
     * Reason for refusing the file that is not tied to a row.
     */
    public ?string $message = null;

    public function isRejected(): bool
    {
        return $this->message !== null || $this->errorCount > 0;
    }

    public function hasPeriodDivergence(): bool
    {
        return $this->rowsOutOfPeriod > 0;
    }

    public function registerDate(CarbonImmutable $date): void
    {
        if ($this->minDate === null || $date->lessThan($this->minDate)) {
            $this->minDate = $date;
        }

        if ($this->maxDate === null || $date->greaterThan($this->maxDate)) {
            $this->maxDate = $date;
        }
    }
}
