<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\File;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class ErrorReportWriter
{
    protected ?Writer $writer = null;

    public function __construct(protected string $absolutePath) {}

    /**
     * Append one error to the report, creating the file on the first call.
     */
    public function add(RowError $error): void
    {
        if ($this->writer === null) {
            File::ensureDirectoryExists(dirname($this->absolutePath));

            $this->writer = new Writer;
            $this->writer->openToFile($this->absolutePath);
            $this->writer->addRow(Row::fromValues([
                __('conciliation.import.report.row'),
                __('conciliation.import.report.column'),
                __('conciliation.import.report.value'),
                __('conciliation.import.report.reason'),
            ]));
        }

        $this->writer->addRow(Row::fromValues([$error->rowNumber, $error->column, $error->value, $error->reason()]));
    }

    public function hasErrors(): bool
    {
        return $this->writer !== null;
    }

    public function close(): void
    {
        $this->writer?->close();
    }
}
