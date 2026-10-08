<?php

namespace App\Services\Import;

use App\Models\ReconciliationSession;
use RuntimeException;

class SessionLockedException extends RuntimeException
{
    public static function for(ReconciliationSession $session): self
    {
        return new self(__('conciliation.sessions.errors.locked', ['status' => $session->status->label()]));
    }
}
