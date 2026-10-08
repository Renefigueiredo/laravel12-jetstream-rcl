<?php

namespace Tests\Unit\Conciliation;

use App\Services\ExcludedCodes\ExcludedCodeFileParser;
use App\Services\ExcludedCodes\ParsedExcludedCodeFile;
use Tests\TestCase;

class ExcludedCodeFileParserTest extends TestCase
{
    /**
     * @param  list<list<mixed>>  $rows  Rows as the reader gives them, starting at row 1
     */
    protected function parse(array $rows): ParsedExcludedCodeFile
    {
        $numbered = [];

        foreach ($rows as $index => $cells) {
            $numbered[$index + 1] = $cells;
        }

        return app(ExcludedCodeFileParser::class)->parse($numbered);
    }

    public function test_template_header_is_skipped_in_any_case(): void
    {
        foreach (['COD_OPERACAO', 'cod_operacao', ' Cod_Operacao '] as $header) {
            $parsed = $this->parse([[$header, 'DESCRICAO'], ['20150652', 'Folha de pagamento']]);

            $this->assertTrue($parsed->isAccepted());
            $this->assertSame([['code' => '20150652', 'description' => 'Folha de pagamento']], $parsed->codes);
        }
    }

    public function test_first_row_without_the_header_is_read_as_a_code(): void
    {
        $parsed = $this->parse([['20150652', 'Folha'], ['11018953', null]]);

        $this->assertTrue($parsed->isAccepted());
        $this->assertSame([
            ['code' => '20150652', 'description' => 'Folha'],
            ['code' => '11018953', 'description' => null],
        ], $parsed->codes);
    }

    public function test_header_text_is_only_a_header_on_the_first_row(): void
    {
        $parsed = $this->parse([['20150652'], ['COD_OPERACAO']]);

        $this->assertFalse($parsed->isAccepted());
        $this->assertSame(2, $parsed->errors[0]['row']);
    }

    public function test_blank_rows_are_skipped(): void
    {
        $parsed = $this->parse([['COD_OPERACAO', 'DESCRICAO'], [null, null], ['', '   '], [], ['20150652', 'Folha'], [null, null, 'ignored']]);

        $this->assertTrue($parsed->isAccepted());
        $this->assertCount(1, $parsed->codes);
    }

    public function test_numeric_cells_are_read_as_text(): void
    {
        $parsed = $this->parse([[20150652.0, 'Folha'], [11018953, 'Convênio'], ['00123', 'Zeros']]);

        $this->assertTrue($parsed->isAccepted());
        $this->assertSame(['20150652', '11018953', '00123'], array_column($parsed->codes, 'code'));
    }

    public function test_spaces_around_code_and_description_are_removed(): void
    {
        $parsed = $this->parse([[' 20150652 ', '  Folha de pagamento  ']]);

        $this->assertSame([['code' => '20150652', 'description' => 'Folha de pagamento']], $parsed->codes);
    }

    public function test_columns_after_the_second_are_ignored(): void
    {
        $parsed = $this->parse([['20150652', 'Folha', 'x-1', str_repeat('z', 500)]]);

        $this->assertTrue($parsed->isAccepted());
        $this->assertSame([['code' => '20150652', 'description' => 'Folha']], $parsed->codes);
    }

    public function test_blank_code_with_description_is_an_error_on_the_code_column(): void
    {
        $parsed = $this->parse([['COD_OPERACAO', 'DESCRICAO'], ['20150652', 'Folha'], [null, 'Sem código']]);

        $this->assertFalse($parsed->isAccepted());
        $this->assertSame([], $parsed->codes);
        $this->assertSame([[
            'row' => 3,
            'column' => 'COD_OPERACAO',
            'reason' => __('conciliation.excluded_codes.import.row_errors.code_blank'),
        ]], $parsed->errors);
    }

    public function test_invalid_codes_are_errors_on_the_code_column(): void
    {
        $parsed = $this->parse([['12-34', null], ['CÓDIGO', null], [str_repeat('9', 21), null], [str_repeat('9', 20), null]]);

        $this->assertFalse($parsed->isAccepted());
        $this->assertSame([1, 2, 3], array_column($parsed->errors, 'row'));
        $this->assertSame(['COD_OPERACAO'], array_values(array_unique(array_column($parsed->errors, 'column'))));
        $this->assertSame(
            [__('conciliation.excluded_codes.import.row_errors.code_invalid')],
            array_values(array_unique(array_column($parsed->errors, 'reason'))),
        );
    }

    public function test_long_description_is_an_error_on_the_description_column(): void
    {
        $parsed = $this->parse([['1', str_repeat('a', 255)], ['2', str_repeat('a', 256)]]);

        $this->assertSame([[
            'row' => 2,
            'column' => 'DESCRICAO',
            'reason' => __('conciliation.excluded_codes.import.row_errors.description_max'),
        ]], $parsed->errors);
    }

    public function test_a_row_with_two_problems_gives_two_errors(): void
    {
        $parsed = $this->parse([['12-34', str_repeat('a', 256)]]);

        $this->assertSame(['COD_OPERACAO', 'DESCRICAO'], array_column($parsed->errors, 'column'));
        $this->assertSame([1, 1], array_column($parsed->errors, 'row'));
    }

    public function test_swapped_columns_are_refused(): void
    {
        $parsed = $this->parse([['Folha de pagamento', '20150652'], ['Convênio de reciprocidade', '11018953']]);

        $this->assertFalse($parsed->isAccepted());
        $this->assertCount(2, $parsed->errors);
    }

    public function test_repeated_codes_count_once(): void
    {
        $parsed = $this->parse([['20150652', 'Primeira'], ['20150652', 'Segunda'], [' 20150652 ', 'Terceira'], ['11018953', null]]);

        $this->assertTrue($parsed->isAccepted());
        $this->assertSame([
            ['code' => '20150652', 'description' => 'Primeira'],
            ['code' => '11018953', 'description' => null],
        ], $parsed->codes);
        $this->assertSame(2, $parsed->repeated);
    }

    public function test_file_without_codes_is_refused(): void
    {
        foreach ([[], [['COD_OPERACAO', 'DESCRICAO']], [['COD_OPERACAO'], [null, null]]] as $rows) {
            $parsed = $this->parse($rows);

            $this->assertFalse($parsed->isAccepted());
            $this->assertSame([], $parsed->errors);
            $this->assertSame(__('conciliation.excluded_codes.import.no_codes'), $parsed->refusal);
        }
    }

    public function test_file_above_the_row_limit_is_refused_without_row_errors(): void
    {
        config(['conciliation.excluded_codes.max_rows' => 3]);

        $atLimit = $this->parse([['COD_OPERACAO'], ['1'], ['2'], ['3']]);
        $aboveLimit = $this->parse([['COD_OPERACAO'], ['1'], ['bad code'], ['3'], ['4']]);

        $this->assertTrue($atLimit->isAccepted());
        $this->assertFalse($aboveLimit->isAccepted());
        $this->assertSame([], $aboveLimit->errors);
        $this->assertSame([], $aboveLimit->codes);
        $this->assertSame(__('conciliation.excluded_codes.import.too_many_rows', ['max' => '3']), $aboveLimit->refusal);
    }

    public function test_reading_stops_after_a_long_run_of_blank_rows(): void
    {
        config(['conciliation.upload.blank_rows_limit' => 2]);

        $parsed = $this->parse([['1'], [null], [null], [null], ['not read: invalid']]);

        $this->assertTrue($parsed->isAccepted());
        $this->assertSame(['1'], array_column($parsed->codes, 'code'));
    }
}
