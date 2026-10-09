<?php

namespace App\Services\Reconciliation;

use App\Contracts\ReconciliationResultInspector;
use App\Models\ReconciliationLink;
use App\Models\ReconciliationSession;

class ReconciliationDecisionInspector implements ReconciliationResultInspector
{
    /**
     * What must be undone before the session can be reopened: links carrying a human decision
     * on its payments, and links of its authorizations to payments of other sessions.
     */
    public function blockingDecisionCount(ReconciliationSession $session): int
    {
        $decisions = ReconciliationLink::query()
            ->join('payment_entries', 'payment_entries.id', '=', 'reconciliation_links.payment_entry_id')
            ->where('payment_entries.reconciliation_session_id', $session->id)
            ->whereNotNull('reconciliation_links.decided_by')
            ->count();

        $paidElsewhere = ReconciliationLink::query()
            ->join('payment_entries', 'payment_entries.id', '=', 'reconciliation_links.payment_entry_id')
            ->join('authorization_entries', 'authorization_entries.id', '=', 'reconciliation_links.authorization_entry_id')
            ->where('authorization_entries.reconciliation_session_id', $session->id)
            ->where('payment_entries.reconciliation_session_id', '<>', $session->id)
            ->count();

        return $decisions + $paidElsewhere;
    }
}
