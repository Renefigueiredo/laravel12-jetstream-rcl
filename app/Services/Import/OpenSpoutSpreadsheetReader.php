<?php

namespace App\Services\Import;

use App\Contracts\SpreadsheetReader;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class OpenSpoutSpreadsheetReader implements SpreadsheetReader
{
    /**
     * Encoding used by the ERP export when the file is not UTF-8.
     */
    protected const LEGACY_ENCODING = 'Windows-1252';

    public function rows(string $absolutePath): iterable
    {
        $reader = $this->open($absolutePath);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $rowNumber = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $rowNumber++;

                    yield $rowNumber => $row->toArray();
                }

                break;
            }
        } finally {
            $reader->close();
        }
    }

    public function sheetCount(string $absolutePath): int
    {
        if ($this->isCsv($absolutePath)) {
            return 1;
        }

        $reader = $this->open($absolutePath);

        try {
            return iterator_count($reader->getSheetIterator());
        } finally {
            $reader->close();
        }
    }

    protected function open(string $absolutePath): ReaderInterface
    {
        $reader = $this->isCsv($absolutePath) ? $this->csvReader($absolutePath) : $this->xlsxReader();

        $reader->open($absolutePath);

        return $reader;
    }

    protected function xlsxReader(): XlsxReader
    {
        $options = new XlsxOptions;
        $options->SHOULD_FORMAT_DATES = false;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

        return new XlsxReader($options);
    }

    protected function csvReader(string $absolutePath): CsvReader
    {
        $options = new CsvOptions;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $options->FIELD_DELIMITER = $this->detectDelimiter($absolutePath);
        $options->ENCODING = $this->isUtf8($absolutePath) ? 'UTF-8' : self::LEGACY_ENCODING;

        return new CsvReader($options);
    }

    protected function isCsv(string $absolutePath): bool
    {
        return strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION)) === 'csv';
    }

    /**
     * The delimiter is the one that appears most in the header line.
     */
    protected function detectDelimiter(string $absolutePath): string
    {
        $handle = fopen($absolutePath, 'rb');
        $headerLine = $handle === false ? '' : (string) fgets($handle);

        if ($handle !== false) {
            fclose($handle);
        }

        return substr_count($headerLine, ';') >= substr_count($headerLine, ',') ? ';' : ',';
    }

    protected function isUtf8(string $absolutePath): bool
    {
        $handle = fopen($absolutePath, 'rb');

        if ($handle === false) {
            return true;
        }

        try {
            while (($line = fgets($handle)) !== false) {
                if (! mb_check_encoding($line, 'UTF-8')) {
                    return false;
                }
            }
        } finally {
            fclose($handle);
        }

        return true;
    }
}
