<?php

namespace App\Enums;

enum DifferenceType: string
{
    case Exact = 'exact';
    case Partial = 'partial';
    case Excess = 'excess';

    public function label(): string
    {
        return __('conciliation.reconciliation.difference_type.'.$this->value);
    }
}
