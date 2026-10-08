<?php

namespace App\Services\Import;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class DateParser
{
    /**
     * Month abbreviations used by the ERP settlement report.
     *
     * @var array<string, int>
     */
    protected const ERP_MONTHS = [
        'JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6,
        'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12,
    ];

    /**
     * Convert a spreadsheet cell into a date without time.
     *
     * Text is accepted as day/month/year (31/05/2026) or in the ERP format (31-MAY-26).
     * Native date cells are read by their value.
     *
     * @throws CellParseException
     */
    public function parse(mixed $value): CarbonImmutable
    {
        if ($value === null) {
            throw new CellParseException('conciliation.errors.date.empty');
        }

        if ($value instanceof DateTimeInterface) {
            return $this->build((int) $value->format('Y'), (int) $value->format('n'), (int) $value->format('j'));
        }

        if (! is_string($value)) {
            throw new CellParseException('conciliation.errors.date.format');
        }

        $text = trim($value);

        if ($text === '') {
            throw new CellParseException('conciliation.errors.date.empty');
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $text, $matches) === 1) {
            return $this->build((int) $matches[3], (int) $matches[2], (int) $matches[1]);
        }

        if (preg_match('/^(\d{1,2})-([A-Za-z]{3})-(\d{2})$/', $text, $matches) === 1) {
            $month = self::ERP_MONTHS[strtoupper($matches[2])] ?? null;

            if ($month === null) {
                throw new CellParseException('conciliation.errors.date.format');
            }

            return $this->build(2000 + (int) $matches[3], $month, (int) $matches[1]);
        }

        throw new CellParseException('conciliation.errors.date.format');
    }

    /**
     * @throws CellParseException
     */
    protected function build(int $year, int $month, int $day): CarbonImmutable
    {
        if (! checkdate($month, $day, $year)) {
            throw new CellParseException('conciliation.errors.date.invalid');
        }

        return CarbonImmutable::create($year, $month, $day, 0, 0, 0, 'UTC');
    }
}
