<?php

namespace App\Http\Controllers;

use App\Enums\SpreadsheetLayoutType;
use App\Services\Import\TemplateWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpreadsheetTemplateController extends Controller
{
    /**
     * Download the template spreadsheet of a layout.
     */
    public function __invoke(string $layout, TemplateWriter $templateWriter): StreamedResponse
    {
        $type = SpreadsheetLayoutType::fromSlug($layout);

        abort_if($type === null, 404);

        return response()->streamDownload(function () use ($templateWriter, $type): void {
            echo $templateWriter->contents($type);
        }, $templateWriter->fileName($type));
    }
}
