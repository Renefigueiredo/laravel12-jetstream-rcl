<?php

namespace App\Enums;

enum StatementLineKind: string
{
    case Payment = 'payment';
    case Discount = 'discount';
    case ToleranceWriteoff = 'tolerance_writeoff';
    case AcceptedSurcharge = 'accepted_surcharge';
    case Overpayment = 'overpayment';

    public function label(): string
    {
        return __('conciliation.dashboard.statement.kinds.'.$this->value);
    }
}
