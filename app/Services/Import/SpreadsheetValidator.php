<?php

namespace App\Services\Import;

use App\Contracts\SpreadsheetLayout;
use App\Contracts\SpreadsheetReader;
use App\Enums\ImportSlot;
use Carbon\CarbonInterface;
use Throwable;

class SpreadsheetValidator
{
    protected const FIRST_ERRORS_LIMIT = 10;

    public function __construct(protected SpreadsheetReader $reader, protected SheetRows $sheetRows) {}

    /**
     * First pass over the file: check headers and every row without persisting anything.
     *
     * @param  CarbonInterface  $period  First day of the session's reference month
     */
    public function validate(string $absolutePath, SpreadsheetLayout $layout, ImportSlot $slot, CarbonInterface $period, ?ErrorReportWriter $errorReport = null): ValidationResult
    {
        $result = new ValidationResult;

        try {
            $this->inspect($absolutePath, $layout, $slot, $period, $result, $errorReport);
        } catch (Throwable $exception) {
            report($exception);

            $result->message = __('conciliation.errors.unreadable');
        } finally {
            $errorReport?->close();
        }

        return $result;
    }

    protected function inspect(string $absolutePath, SpreadsheetLayout $layout, ImportSlot $slot, CarbonInterface $period, ValidationResult $result, ?ErrorReportWriter $errorReport): void
    {
        $result->sheetCount = $this->reader->sheetCount($absolutePath);

        $headers = $this->sheetRows->headers($absolutePath);

        if ($headers === null) {
            $result->message = __('conciliation.errors.no_entries');

            return;
        }

        $missingRequired = array_values(array_diff($layout->requiredHeaders(), array_keys($headers)));

        if ($missingRequired !== []) {
            $result->message = __('conciliation.errors.missing_headers', [
                'columns' => implode(', ', $missingRequired),
                'layout' => $layout->type()->label(),
            ]);

            return;
        }

        $result->missingColumns = array_values(array_diff($layout->headers(), array_keys($headers)));

        foreach ($this->sheetRows->parsed($absolutePath, $layout, $slot) as $parsed) {
            $result->rowsTotal++;

            if ($parsed === null) {
                $result->rowsSkippedValue++;

                continue;
            }

            if (is_array($parsed)) {
                $this->registerErrors($parsed, $result, $errorReport);

                continue;
            }

            $result->rowsValid++;
            $result->registerDate($parsed->periodDate);

            if ($parsed->periodDate->format('Y-m') !== $period->format('Y-m')) {
                $result->rowsOutOfPeriod++;
            }
        }

        if ($result->errorCount > 0 || $result->rowsValid > 0) {
            return;
        }

        $result->message = $result->rowsSkippedValue > 0
            ? __('conciliation.errors.no_valid_entries')
            : __('conciliation.errors.no_entries');
    }

    /**
     * @param  list<RowError>  $errors
     */
    protected function registerErrors(array $errors, ValidationResult $result, ?ErrorReportWriter $errorReport): void
    {
        foreach ($errors as $error) {
            $result->errorCount++;

            if (count($result->firstErrors) < self::FIRST_ERRORS_LIMIT) {
                $result->firstErrors[] = $error->toArray();
            }

            $errorReport?->add($error);
        }
    }
}
