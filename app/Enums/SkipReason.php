<?php

namespace App\Enums;

enum SkipReason: string
{
    case ExcludedCode = 'excluded_code';
    case DuplicateOfOtherPeriod = 'duplicate_of_other_period';

    public function label(): string
    {
        return __('conciliation.reconciliation.skip_reason.'.$this->value);
    }
}
