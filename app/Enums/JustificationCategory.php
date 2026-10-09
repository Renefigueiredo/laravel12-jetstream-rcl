<?php

namespace App\Enums;

enum JustificationCategory: string
{
    case CommercialDiscount = 'commercial_discount';
    case InterestOrFine = 'interest_or_fine';
    case Freight = 'freight';
    case PriceAdjustment = 'price_adjustment';
    case Rounding = 'rounding';
    case WithinTolerance = 'within_tolerance';
    case Other = 'other';

    public function label(): string
    {
        return __('conciliation.reconciliation.justification_category.'.$this->value);
    }
}
