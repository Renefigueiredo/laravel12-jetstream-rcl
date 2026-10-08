<?php

namespace App\Contracts;

use App\Models\ReconciliationSession;

interface ReconciliationResultInspector
{
    /**
     * Count what prevents the session from being reopened: manual confirmations, manual
     * links and write-offs, plus links with entries of other sessions.
     */
    public function blockingDecisionCount(ReconciliationSession $session): int;
}
