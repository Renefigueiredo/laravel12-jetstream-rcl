<?php

namespace App\Enums;

enum UserPermission: string
{
    case ManageExcludedCodes = 'manage_excluded_codes';
    case ConfigureTolerance = 'configure_tolerance';

    public function label(): string
    {
        return __('conciliation.permissions.'.$this->value);
    }
}
