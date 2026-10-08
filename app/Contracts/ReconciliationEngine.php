<?php

namespace App\Contracts;

use App\Models\ReconciliationSession;

interface ReconciliationEngine
{
    /**
     * Reconcile the active entries of the session.
     *
     * The engine reads the entries and writes only its own result: it does not change the
     * session, its files or its entries.
     *
     * @param  callable(int $percent): void  $reportProgress
     */
    public function run(ReconciliationSession $session, callable $reportProgress): void;

    /**
     * Remove the previous result of the session. Called before a run and after a failure.
     */
    public function discardResult(ReconciliationSession $session): void;
}
