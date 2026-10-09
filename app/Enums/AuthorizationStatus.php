<?php

namespace App\Enums;

enum AuthorizationStatus: string
{
    case Open = 'open';
    case Partial = 'partial';
    case Reconciled = 'reconciled';

    public function label(): string
    {
        return __('conciliation.reconciliation.authorization_status.'.$this->value);
    }
}
