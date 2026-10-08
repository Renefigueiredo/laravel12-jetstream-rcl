<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrador = 'administrador';
    case Operador = 'operador';

    public function label(): string
    {
        return __('conciliation.roles.'.$this->value);
    }
}
