<?php

namespace App\Actions\Conciliation;

use RuntimeException;

class ComplementarySessionConfirmationRequired extends RuntimeException
{
    public function __construct(public readonly string $periodLabel)
    {
        parent::__construct(__('conciliation.sessions.complementary.warning', ['period' => $periodLabel]));
    }
}
