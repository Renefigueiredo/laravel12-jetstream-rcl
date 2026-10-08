<?php

namespace Tests\Unit\Conciliation;

use App\Services\Import\CellParseException;
use App\Services\Import\DateParser;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DateParserTest extends TestCase
{
    /**
     * @return array<string, array{mixed, string}>
     */
    public static function acceptedDates(): array
    {
        return [
            'erp format' => ['31-JUL-26', '2026-07-31'],
            'erp format lower case' => ['01-dec-25', '2025-12-01'],
            'erp format may' => ['29-MAY-26', '2026-05-29'],
            'erp format single digit day' => ['1-FEB-26', '2026-02-01'],
            'brazilian format' => ['31/05/2026', '2026-05-31'],
            'brazilian format without leading zeros' => ['1/5/2026', '2026-05-01'],
            'surrounding spaces' => [' 27/07/2026 ', '2026-07-27'],
            'leap day' => ['29/02/2028', '2028-02-29'],
            'native date cell' => [new DateTimeImmutable('2026-07-27 00:00:00'), '2026-07-27'],
            'native date cell with time' => [new DateTimeImmutable('2026-07-27 15:45:10'), '2026-07-27'],
        ];
    }

    #[DataProvider('acceptedDates')]
    public function test_it_parses_accepted_formats(mixed $input, string $expectedDate): void
    {
        $this->assertSame($expectedDate, (new DateParser)->parse($input)->format('Y-m-d'));
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function rejectedDates(): array
    {
        return [
            'empty string' => ['', 'conciliation.errors.date.empty'],
            'null' => [null, 'conciliation.errors.date.empty'],
            'iso format' => ['2026-05-31', 'conciliation.errors.date.format'],
            'american format' => ['05/31/2026', 'conciliation.errors.date.invalid'],
            'nonexistent day' => ['31/02/2026', 'conciliation.errors.date.invalid'],
            'nonexistent erp day' => ['31-FEB-26', 'conciliation.errors.date.invalid'],
            'portuguese month abbreviation' => ['31-AGO-26', 'conciliation.errors.date.format'],
            'two digit year in brazilian format' => ['31/05/26', 'conciliation.errors.date.format'],
            'four digit year in erp format' => ['31-JUL-2026', 'conciliation.errors.date.format'],
            'text' => ['ontem', 'conciliation.errors.date.format'],
            'number' => [45000, 'conciliation.errors.date.format'],
        ];
    }

    #[DataProvider('rejectedDates')]
    public function test_it_rejects_invalid_dates_with_a_reason(mixed $input, string $expectedReason): void
    {
        try {
            (new DateParser)->parse($input);
            $this->fail('The date should have been rejected.');
        } catch (CellParseException $exception) {
            $this->assertSame($expectedReason, $exception->reasonKey);
        }
    }
}
