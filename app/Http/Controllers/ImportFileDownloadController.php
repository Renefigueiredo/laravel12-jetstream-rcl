<?php

namespace App\Http\Controllers;

use App\Models\ImportFile;
use App\Models\ReconciliationSession;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportFileDownloadController extends Controller
{
    /**
     * Download the exact copy of a spreadsheet accepted in a session.
     */
    public function __invoke(ReconciliationSession $session, ImportFile $importFile): StreamedResponse
    {
        Gate::authorize('view', $session);

        abort_unless($importFile->reconciliation_session_id === $session->id, 404);

        return Storage::disk($importFile->disk)->download($importFile->path, $importFile->original_name);
    }
}
