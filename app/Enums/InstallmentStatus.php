<?php

namespace App\Enums;

enum InstallmentStatus: string
{
    case Paid = 'paid';
    case Open = 'open';
    case Overdue = 'overdue';

    public function label(): string
    {
        return __('conciliation.dashboard.installment_status.'.$this->value);
    }
}
