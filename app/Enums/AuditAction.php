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

    public function label(): string
    {
        return __('conciliation.audit.actions.'.$this->value);
    }
}
