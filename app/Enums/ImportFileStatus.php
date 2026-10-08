<?php

namespace App\Enums;

enum ImportFileStatus: string
{
    case Active = 'active';
    case Replaced = 'replaced';
}
