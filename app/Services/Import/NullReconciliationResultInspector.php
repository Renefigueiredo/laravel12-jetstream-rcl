<?php

namespace App\Services\Import;

use App\Contracts\ReconciliationResultInspector;
use App\Models\ReconciliationSession;

/**
 * Used until the reconciliation modules provide the real inspector.
 */
class NullReconciliationResultInspector implements ReconciliationResultInspector
{
    public function blockingDecisionCount(ReconciliationSession $session): int
    {
        return 0;
    }
}
