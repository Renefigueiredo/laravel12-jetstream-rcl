<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('conciliation:prune-import-attempts')->everyTenMinutes()->withoutOverlapping();
Schedule::command('conciliation:recover-stuck-sessions')->everyTenMinutes()->withoutOverlapping();
