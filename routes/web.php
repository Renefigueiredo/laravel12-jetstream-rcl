<?php

use App\Http\Controllers\ExcludedCodeTemplateController;
use App\Http\Controllers\ImportErrorReportController;
use App\Http\Controllers\ImportFileDownloadController;
use App\Http\Controllers\SpreadsheetTemplateController;
use App\Livewire\ExcludedCodes\Index as ExcludedCodesIndex;
use App\Livewire\Sessions\History;
use App\Livewire\Sessions\Index;
use App\Livewire\Sessions\Show;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::livewire('/sessoes', Index::class)->name('sessions.index');

    Route::livewire('/sessoes/historico', History::class)
        ->can('view-session-history')
        ->name('sessions.history');

    Route::get('/sessoes/tentativas/{importAttempt}/erros', ImportErrorReportController::class)
        ->name('sessions.attempts.errors');

    Route::livewire('/sessoes/{session}', Show::class)
        ->whereNumber('session')
        ->name('sessions.show');

    Route::get('/sessoes/{session}/arquivos/{importFile}/original', ImportFileDownloadController::class)
        ->whereNumber('session')
        ->name('sessions.files.download');

    Route::get('/planilhas-modelo/{layout}', SpreadsheetTemplateController::class)
        ->name('templates.download');

    Route::livewire('/codigos-excluidos', ExcludedCodesIndex::class)
        ->can('manage-excluded-codes')
        ->name('excluded-codes.index');

    Route::get('/codigos-excluidos/modelo', ExcludedCodeTemplateController::class)
        ->can('manage-excluded-codes')
        ->name('excluded-codes.template');
});
