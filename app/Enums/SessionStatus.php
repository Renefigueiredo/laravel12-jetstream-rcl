<?php

namespace App\Enums;

enum SessionStatus: string
{
    case Open = 'open';
    case Processing = 'processing';
    case Processed = 'processed';

    public function label(): string
    {
        return __('conciliation.sessions.status.'.$this->value);
    }
}
