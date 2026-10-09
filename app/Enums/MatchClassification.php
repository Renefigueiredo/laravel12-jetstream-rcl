<?php

namespace App\Enums;

enum MatchClassification: string
{
    case Automatic = 'automatic';
    case Installment = 'installment';
    case Doubtful = 'doubtful';
    case Partial = 'partial';
    case Excess = 'excess';

    public function label(): string
    {
        return __('conciliation.reconciliation.classification.'.$this->value);
    }
}
