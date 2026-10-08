<?php

namespace App\Services\Import;

use App\Contracts\SpreadsheetLayout;
use App\Enums\SpreadsheetLayoutType;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Options as CsvOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

class TemplateWriter
{
    public function __construct(protected LayoutRegistry $layouts) {}

    /**
     * The file name offered to the user for the template of a layout.
     */
    public function fileName(SpreadsheetLayoutType $type): string
    {
        return 'modelo-'.$type->slug().'.'.$this->extension($type);
    }

    /**
     * Build the template spreadsheet: the layout headers and one fictitious row.
     */
    public function contents(SpreadsheetLayoutType $type): string
    {
        $layout = $this->layouts->for($type);
        $path = tempnam(sys_get_temp_dir(), 'conciliation-template-');

        try {
            $writer = $this->writer($type);
            $writer->openToFile($path);
            $writer->addRow(Row::fromValues($layout->headers()));
            $writer->addRow(Row::fromValues($this->exampleCells($layout)));
            $writer->close();

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    protected function extension(SpreadsheetLayoutType $type): string
    {
        return $type === SpreadsheetLayoutType::Payments ? 'csv' : 'xlsx';
    }

    protected function writer(SpreadsheetLayoutType $type): CsvWriter|XlsxWriter
    {
        if ($type === SpreadsheetLayoutType::Payments) {
            $options = new CsvOptions;
            $options->FIELD_DELIMITER = ';';

            return new CsvWriter($options);
        }

        return new XlsxWriter;
    }

    /**
     * @return list<string>
     */
    protected function exampleCells(SpreadsheetLayout $layout): array
    {
        $example = $layout->exampleRow();

        return array_map(fn (string $header): string => $example[$header] ?? '', $layout->headers());
    }
}
