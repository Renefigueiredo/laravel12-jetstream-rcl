<?php

namespace App\Enums;

enum ExcludedCodeSource: string
{
    case Manual = 'manual';
    case File = 'file';

    public function label(): string
    {
        return __('conciliation.excluded_codes.source.'.$this->value);
    }
}
