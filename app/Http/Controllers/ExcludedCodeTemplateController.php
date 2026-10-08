<?php

namespace App\Http\Controllers;

use App\Services\ExcludedCodes\ExcludedCodeTemplateWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcludedCodeTemplateController extends Controller
{
    /**
     * Download the template spreadsheet for importing excluded operation codes.
     */
    public function __invoke(ExcludedCodeTemplateWriter $templateWriter): StreamedResponse
    {
        return response()->streamDownload(function () use ($templateWriter): void {
            echo $templateWriter->contents();
        }, $templateWriter->fileName());
    }
}
