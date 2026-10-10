<?php

namespace App\Providers;

use App\Contracts\ReconciliationEngine;
use App\Contracts\ReconciliationResultInspector;
use App\Contracts\SpreadsheetReader;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\AuthorizationEntry;
use App\Models\ExcludedCodeImport;
use App\Models\ExcludedOperationCode;
use App\Models\InstallmentPlan;
use App\Models\ReconciliationLink;
use App\Models\ReconciliationRun;
use App\Models\ReconciliationSession;
use App\Models\ReconciliationSettings;
use App\Models\ReconciliationSuggestion;
use App\Models\User;
use App\Models\UserPermissionGrant;
use App\Services\Import\OpenSpoutSpreadsheetReader;
use App\Services\Reconciliation\DatabaseReconciliationEngine;
use App\Services\Reconciliation\ReconciliationDecisionInspector;
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
        $this->app->bind(ReconciliationEngine::class, DatabaseReconciliationEngine::class);
        $this->app->bind(ReconciliationResultInspector::class, ReconciliationDecisionInspector::class);
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
            'reconciliation_run' => ReconciliationRun::class,
            'reconciliation_link' => ReconciliationLink::class,
            'reconciliation_suggestion' => ReconciliationSuggestion::class,
            'authorization_entry' => AuthorizationEntry::class,
            'reconciliation_settings' => ReconciliationSettings::class,
            'installment_plan' => InstallmentPlan::class,
        ]);

        Gate::define('view-session-history', fn (User $user): bool => $user->role === UserRole::Administrador);
        Gate::define('create-matching-authorization', fn (User $user): bool => $user->role === UserRole::Administrador);
        Gate::define('configure-tolerance', fn (User $user): bool => $user->hasPermission(UserPermission::ConfigureTolerance));
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
