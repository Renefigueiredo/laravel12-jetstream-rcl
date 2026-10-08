<?php

namespace App\Services\ExcludedCodes;

use App\Services\Import\CellText;

class ExcludedCodeFileParser
{
    public const CODE_COLUMN = 'COD_OPERACAO';

    public const DESCRIPTION_COLUMN = 'DESCRICAO';

    /**
     * Read the codes by column position: the code in the first column, the description in the second.
     *
     * Nothing is accepted when any row is invalid.
     *
     * @param  iterable<int, array<int, mixed>>  $rows  Rows keyed by their number in the file
     */
    public function parse(iterable $rows): ParsedExcludedCodeFile
    {
        $maxRows = (int) config('conciliation.excluded_codes.max_rows');
        $blankRowsLimit = (int) config('conciliation.upload.blank_rows_limit');

        $codes = [];
        $seen = [];
        $errors = [];
        $repeated = 0;
        $dataRows = 0;
        $blankRows = 0;

        foreach ($rows as $rowNumber => $cells) {
            $code = OperationCode::normalize(CellText::from($cells[0] ?? null));
            $description = CellText::from($cells[1] ?? null);

            if ($rowNumber === 1 && $code !== null && strcasecmp($code, self::CODE_COLUMN) === 0) {
                continue;
            }

            if ($code === null && $description === null) {
                if (++$blankRows > $blankRowsLimit) {
                    break;
                }

                continue;
            }

            $blankRows = 0;

            if (++$dataRows > $maxRows) {
                return ParsedExcludedCodeFile::refused(__('conciliation.excluded_codes.import.too_many_rows', [
                    'max' => number_format($maxRows, 0, ',', '.'),
                ]));
            }

            $rowErrors = $this->rowErrors($rowNumber, $code, $description);

            if ($rowErrors !== []) {
                array_push($errors, ...$rowErrors);

                continue;
            }

            if (isset($seen[$code])) {
                $repeated++;

                continue;
            }

            $seen[$code] = true;
            $codes[] = ['code' => $code, 'description' => $description];
        }

        if ($errors !== []) {
            return new ParsedExcludedCodeFile(errors: $errors);
        }

        if ($codes === []) {
            return ParsedExcludedCodeFile::refused(__('conciliation.excluded_codes.import.no_codes'));
        }

        return new ParsedExcludedCodeFile(codes: $codes, repeated: $repeated);
    }

    /**
     * @return list<array{row: int, column: string, reason: string}>
     */
    protected function rowErrors(int $rowNumber, ?string $code, ?string $description): array
    {
        $errors = [];

        if ($code === null) {
            $errors[] = $this->error($rowNumber, self::CODE_COLUMN, 'code_blank');
        } elseif (! OperationCode::isValid($code)) {
            $errors[] = $this->error($rowNumber, self::CODE_COLUMN, 'code_invalid');
        }

        if ($description !== null && mb_strlen($description) > OperationCode::DESCRIPTION_MAX_LENGTH) {
            $errors[] = $this->error($rowNumber, self::DESCRIPTION_COLUMN, 'description_max');
        }

        return $errors;
    }

    /**
     * @return array{row: int, column: string, reason: string}
     */
    protected function error(int $rowNumber, string $column, string $reason): array
    {
        return [
            'row' => $rowNumber,
            'column' => $column,
            'reason' => __('conciliation.excluded_codes.import.row_errors.'.$reason),
        ];
    }
}
