<?php

namespace App\Enums;

enum PendingItemKind: string
{
    case Suggestion = 'suggestion';
    case UnmatchedAuthorization = 'unmatched_authorization';
    case OpenBalance = 'open_balance';
    case UnmatchedPayment = 'unmatched_payment';

    public function label(): string
    {
        return __('conciliation.reconciliation.item_kind.'.$this->value);
    }
}
