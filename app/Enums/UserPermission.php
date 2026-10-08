<?php

namespace App\Enums;

enum UserPermission: string
{
    case ManageExcludedCodes = 'manage_excluded_codes';

    public function label(): string
    {
        return __('conciliation.permissions.'.$this->value);
    }
}
