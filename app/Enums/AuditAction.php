<?php

namespace App\Enums;

enum AuditAction: string
{
    case SessionCreated = 'session_created';
    case PeriodDivergenceConfirmed = 'period_divergence_confirmed';
    case FileReplaced = 'file_replaced';
    case ReconciliationRequested = 'reconciliation_requested';
    case SessionReopened = 'session_reopened';
    case SessionDeleted = 'session_deleted';
    case ExcludedCodeAdded = 'excluded_code_added';
    case ExcludedCodesImported = 'excluded_codes_imported';
    case ExcludedCodeRemoved = 'excluded_code_removed';
    case PermissionGranted = 'permission_granted';
    case PermissionRevoked = 'permission_revoked';
    case ReconciliationCompleted = 'reconciliation_completed';
    case SuggestionConfirmed = 'suggestion_confirmed';
    case SuggestionRejected = 'suggestion_rejected';
    case ManualLinkCreated = 'manual_link_created';
    case LinkRemoved = 'link_removed';
    case AuthorizationClosedWithDiscount = 'authorization_closed_with_discount';
    case AuthorizationCreatedInReconciliation = 'authorization_created_in_reconciliation';
    case ReconciliationSettingsChanged = 'reconciliation_settings_changed';
    case InstallmentPlanSaved = 'installment_plan_saved';
    case InstallmentPlanRemoved = 'installment_plan_removed';

    public function label(): string
    {
        return __('conciliation.audit.actions.'.$this->value);
    }
}
