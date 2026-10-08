<?php

namespace App\Services\ExcludedCodes;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class ExcludedCodeTemplateWriter
{
    public const EXAMPLE_CODE = '20150652';

    /**
     * The file name offered to the user for the template.
     */
    public function fileName(): string
    {
        return __('conciliation.excluded_codes.import.template_filename');
    }

    /**
     * Build the template spreadsheet: the two headers and one example row, with the code as text.
     */
    public function contents(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'conciliation-template-');

        try {
            $writer = new Writer;
            $writer->openToFile($path);
            $writer->addRow(Row::fromValues([ExcludedCodeFileParser::CODE_COLUMN, ExcludedCodeFileParser::DESCRIPTION_COLUMN]));
            $writer->addRow(Row::fromValues([self::EXAMPLE_CODE, __('conciliation.excluded_codes.import.template_description')]));
            $writer->close();

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }
}
