<?php

namespace App\Services\Import;

class MoneyParser
{
    /**
     * Convert a spreadsheet cell into integer cents.
     *
     * Text is accepted in Brazilian (1.234,56) or international (1,234.56) format, with or
     * without the currency symbol. Native numeric cells are read by their value.
     *
     * @throws CellParseException
     */
    public function parse(mixed $value): int
    {
        if ($value === null) {
            throw new CellParseException('conciliation.errors.money.empty');
        }

        if (is_int($value)) {
            return $value * 100;
        }

        if (is_float($value)) {
            return $this->fromFloat($value);
        }

        if (! is_string($value)) {
            throw new CellParseException('conciliation.errors.money.format');
        }

        return $this->fromText($value);
    }

    /**
     * @throws CellParseException
     */
    protected function fromFloat(float $value): int
    {
        $cents = round($value * 100);

        if (abs($value * 100 - $cents) > 0.000001) {
            throw new CellParseException('conciliation.errors.money.decimals');
        }

        return (int) $cents;
    }

    /**
     * @throws CellParseException
     */
    protected function fromText(string $value): int
    {
        $text = preg_replace('/[\s\x{00A0}]+/u', '', $value) ?? '';
        $text = str_ireplace('R$', '', $text);

        if ($text === '') {
            throw new CellParseException('conciliation.errors.money.empty');
        }

        $isNegative = str_starts_with($text, '-');
        $text = $isNegative ? substr($text, 1) : $text;

        if (preg_match('/^[0-9.,]+$/', $text) !== 1 || preg_match('/[0-9]/', $text) !== 1) {
            throw new CellParseException('conciliation.errors.money.format');
        }

        [$integerPart, $decimalPart] = $this->splitParts($text);

        $cents = ((int) ($integerPart === '' ? '0' : $integerPart)) * 100 + (int) str_pad($decimalPart, 2, '0');

        return $isNegative ? -$cents : $cents;
    }

    /**
     * Split the digits into the integer part and the decimal part (zero to two digits).
     *
     * @return array{string, string}
     *
     * @throws CellParseException
     */
    protected function splitParts(string $text): array
    {
        $hasDot = str_contains($text, '.');
        $hasComma = str_contains($text, ',');

        if (! $hasDot && ! $hasComma) {
            return [$text, ''];
        }

        if ($hasDot && $hasComma) {
            $decimalSeparator = strrpos($text, '.') > strrpos($text, ',') ? '.' : ',';
            $thousandsSeparator = $decimalSeparator === '.' ? ',' : '.';

            if (substr_count($text, $decimalSeparator) !== 1) {
                throw new CellParseException('conciliation.errors.money.format');
            }

            [$integerPart, $decimalPart] = explode($decimalSeparator, $text);

            return [$this->removeThousands($integerPart, $thousandsSeparator), $this->decimalDigits($decimalPart)];
        }

        $separator = $hasDot ? '.' : ',';

        if (substr_count($text, $separator) > 1) {
            return [$this->removeThousands($text, $separator), ''];
        }

        [$integerPart, $fraction] = explode($separator, $text);

        if (strlen($fraction) === 3 && $integerPart !== '') {
            return [$this->removeThousands($text, $separator), ''];
        }

        return [$integerPart, $this->decimalDigits($fraction)];
    }

    /**
     * @throws CellParseException
     */
    protected function decimalDigits(string $fraction): string
    {
        if ($fraction === '') {
            throw new CellParseException('conciliation.errors.money.format');
        }

        if (strlen($fraction) > 2) {
            throw new CellParseException('conciliation.errors.money.decimals');
        }

        return $fraction;
    }

    /**
     * @throws CellParseException
     */
    protected function removeThousands(string $integerPart, string $separator): string
    {
        $groups = explode($separator, $integerPart);
        $firstGroup = array_shift($groups);

        if ($firstGroup === '' || strlen($firstGroup) > 3) {
            throw new CellParseException('conciliation.errors.money.format');
        }

        foreach ($groups as $group) {
            if (strlen($group) !== 3) {
                throw new CellParseException('conciliation.errors.money.format');
            }
        }

        return $firstGroup.implode('', $groups);
    }
}
