<?php

namespace App\Enums;

enum SuggestionStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Superseded = 'superseded';

    public function label(): string
    {
        return __('conciliation.reconciliation.suggestion_status.'.$this->value);
    }
}
