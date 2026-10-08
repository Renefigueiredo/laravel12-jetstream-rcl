<?php

namespace App\Providers;

use App\Contracts\ReconciliationResultInspector;
use App\Contracts\SpreadsheetReader;
use App\Enums\UserRole;
use App\Models\ReconciliationSession;
use App\Models\User;
use App\Services\Import\NullReconciliationResultInspector;
use App\Services\Import\OpenSpoutSpreadsheetReader;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SpreadsheetReader::class, OpenSpoutSpreadsheetReader::class);
        $this->app->bind(ReconciliationResultInspector::class, NullReconciliationResultInspector::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'reconciliation_session' => ReconciliationSession::class,
        ]);

        Gate::define('view-session-history', fn (User $user): bool => $user->role === UserRole::Administrador);

        seo()
            ->site('Promovaweb')
            ->title(
                default: 'Laravel 12 Jetstream Livewire Starter Kit',
                modify: fn (string $title) => $title.' | Promovaweb'
            )
            ->description(default: 'We are a development agency ...')
            ->twitterSite('@promovaweb');
    }
}
