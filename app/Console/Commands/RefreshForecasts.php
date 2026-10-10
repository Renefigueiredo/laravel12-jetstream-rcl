<?php

namespace App\Console\Commands;

use App\Models\AuthorizationForecast;
use App\Services\Reconciliation\InstallmentForecaster;
use Illuminate\Console\Command;

class RefreshForecasts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'conciliation:refresh-forecasts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild the forecast of instalments of every authorization with something left to pay';

    /**
     * Execute the console command.
     */
    public function handle(InstallmentForecaster $forecaster): int
    {
        $forecaster->refreshAll();

        $this->info(__('conciliation.dashboard.forecast.refreshed', ['count' => AuthorizationForecast::query()->count()]));

        return self::SUCCESS;
    }
}
