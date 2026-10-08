<?php

namespace App\Http\Controllers;

use App\Models\ImportAttempt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportErrorReportController extends Controller
{
    /**
     * Download the full validation error report of a refused spreadsheet.
     */
    public function __invoke(ImportAttempt $importAttempt): StreamedResponse
    {
        Gate::authorize('view', $importAttempt->session);

        abort_if($importAttempt->error_report_path === null, 404);
        abort_unless(Storage::disk($importAttempt->disk)->exists($importAttempt->error_report_path), 404);

        return Storage::disk($importAttempt->disk)->download(
            $importAttempt->error_report_path,
            __('conciliation.import.report.filename', ['name' => pathinfo($importAttempt->original_name, PATHINFO_FILENAME)]),
        );
    }
}
