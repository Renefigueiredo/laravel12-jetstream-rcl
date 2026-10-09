<?php

namespace App\Enums;

enum DifferenceTreatment: string
{
    case StillOwed = 'still_owed';
    case Discount = 'discount';
    case AcceptedSurcharge = 'accepted_surcharge';
    case Overpayment = 'overpayment';

    public function label(): string
    {
        return __('conciliation.reconciliation.treatment.'.$this->value);
    }
}
