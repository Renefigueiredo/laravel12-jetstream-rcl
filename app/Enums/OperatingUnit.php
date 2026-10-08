<?php

namespace App\Enums;

enum OperatingUnit: string
{
    case Social = 'social';
    case Saude = 'saude';

    public function label(): string
    {
        return __('conciliation.units.'.$this->value);
    }
}
