<?php

namespace App\Enums;

enum LinkOrigin: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';

    public function label(): string
    {
        return __('conciliation.reconciliation.origin.'.$this->value);
    }
}
