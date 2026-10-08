<?php

namespace App\Services\Import;

use RuntimeException;

class CellParseException extends RuntimeException
{
    /**
     * @param  string  $reasonKey  Translation key of the reason shown to the user
     */
    public function __construct(public readonly string $reasonKey)
    {
        parent::__construct($reasonKey);
    }
}
