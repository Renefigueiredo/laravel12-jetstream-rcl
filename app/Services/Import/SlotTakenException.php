<?php

namespace App\Services\Import;

use RuntimeException;

class SlotTakenException extends RuntimeException
{
    public static function make(): self
    {
        return new self(__('conciliation.errors.slot_taken'));
    }
}
