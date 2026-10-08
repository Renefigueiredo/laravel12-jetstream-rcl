<?php

namespace App\Providers;

use App\Contracts\ReconciliationResultInspector;
use App\Contracts\SpreadsheetReader;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\ExcludedCodeImport;
use App\Models\ExcludedOperationCode;
use App\Models\ReconciliationSession;
use App\Models\User;
use App\Models\UserPermissionGrant;
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
            'excluded_operation_code' => ExcludedOperationCode::class,
            'excluded_code_import' => ExcludedCodeImport::class,
            'user_permission' => UserPermissionGrant::class,
        ]);

        Gate::define('view-session-history', fn (User $user): bool => $user->role === UserRole::Administrador);
        Gate::define('manage-excluded-codes', fn (User $user): bool => $user->hasPermission(UserPermission::ManageExcludedCodes));

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
