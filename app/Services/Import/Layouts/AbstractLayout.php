<?php

namespace App\Services\Import\Layouts;

use App\Contracts\SpreadsheetLayout;
use App\Enums\ImportSlot;
use App\Services\Import\CellParseException;
use App\Services\Import\CellText;
use App\Services\Import\DateParser;
use App\Services\Import\MoneyParser;
use App\Services\Import\ParsedRow;
use App\Services\Import\RowError;
use Carbon\CarbonImmutable;

abstract class AbstractLayout implements SpreadsheetLayout
{
    protected const TEXT = 'text';

    protected const MONEY = 'money';

    protected const DATE = 'date';

    public function __construct(protected MoneyParser $moneyParser, protected DateParser $dateParser) {}

    /**
     * Columns read into typed attributes.
     *
     * @return array<string, array{attribute: string, type: string, required: bool}>
     */
    abstract protected function columns(): array;

    /**
     * @param  array<string, mixed>  $attributes
     */
    abstract protected function identityKey(array $attributes, ImportSlot $slot): string;

    /**
     * Attributes that do not come from a column, such as the operating unit of the slot.
     *
     * @return array<string, mixed>
     */
    protected function slotAttributes(ImportSlot $slot): array
    {
        return [];
    }

    /**
     * Attributes computed from the ones already read from the row.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function derivedAttributes(array $attributes): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function requiredHeaders(): array
    {
        return array_keys(array_filter($this->columns(), fn (array $column): bool => $column['required']));
    }

    public function parse(array $row, array $raw, int $rowNumber, ImportSlot $slot): ParsedRow|array|null
    {
        $errors = [];
        $attributes = [];
        $columns = $this->columns();
        $amountColumn = $this->amountColumn();

        try {
            $amount = $this->moneyParser->parse($this->moneyInput($row[$amountColumn] ?? null));

            if ($amount <= 0) {
                return null;
            }

            $attributes[$columns[$amountColumn]['attribute']] = $amount;
        } catch (CellParseException $exception) {
            $errors[] = $this->error($rowNumber, $amountColumn, $row[$amountColumn] ?? null, $exception->reasonKey);
        }

        foreach ($columns as $header => $column) {
            if ($header === $amountColumn) {
                continue;
            }

            $cell = $row[$header] ?? null;

            try {
                $attributes[$column['attribute']] = $this->parseCell($cell, $column);
            } catch (CellParseException $exception) {
                $errors[] = $this->error($rowNumber, $header, $cell, $exception->reasonKey);
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        $attributes = [...$attributes, ...$this->slotAttributes($slot)];
        $attributes = [...$attributes, ...$this->derivedAttributes($attributes)];

        /** @var CarbonImmutable $periodDate */
        $periodDate = $attributes[$columns[$this->periodDateColumn()]['attribute']];

        return new ParsedRow($rowNumber, $attributes, $this->identityKey($attributes, $slot), $periodDate, $raw);
    }

    /**
     * @param  array{attribute: string, type: string, required: bool}  $column
     *
     * @throws CellParseException
     */
    protected function parseCell(mixed $cell, array $column): mixed
    {
        return match ($column['type']) {
            self::MONEY => $this->moneyParser->parse($this->moneyInput($cell)),
            self::DATE => $this->dateParser->parse(is_string($cell) && trim($cell) === '' ? null : $cell),
            default => $this->parseText($cell, $column['required']),
        };
    }

    /**
     * @throws CellParseException
     */
    protected function parseText(mixed $cell, bool $required): ?string
    {
        $text = CellText::from($cell);

        if ($text === null && $required) {
            throw new CellParseException('conciliation.errors.required');
        }

        return $text;
    }

    /**
     * Treat an empty text cell as a missing amount.
     */
    protected function moneyInput(mixed $cell): mixed
    {
        return is_string($cell) && trim($cell) === '' ? null : $cell;
    }

    protected function error(int $rowNumber, string $column, mixed $cell, string $reasonKey): RowError
    {
        return new RowError($rowNumber, $column, CellText::from($cell) ?? '', $reasonKey);
    }

    protected function normalizeForIdentity(?string $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }
}
