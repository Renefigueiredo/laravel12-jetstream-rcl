<?php

namespace App\Services\Reconciliation;

use App\Services\Reconciliation\Matching\AuthorizationCandidate;
use App\Services\Reconciliation\Matching\PaymentCandidate;

final readonly class LoadedCandidates
{
    /**
     * @param  list<AuthorizationCandidate>  $authorizations
     * @param  list<PaymentCandidate>  $payments
     * @param  list<array<string, mixed>>  $skips  Rows for reconciliation_skips, without the run
     * @param  list<string>  $blockedPairs
     */
    public function __construct(
        public array $authorizations,
        public array $payments,
        public array $skips,
        public array $blockedPairs,
    ) {}

    /**
     * @return array<int, int> Balance read for each authorization, by id
     */
    public function balances(): array
    {
        $balances = [];

        foreach ($this->authorizations as $authorization) {
            $balances[$authorization->id] = $authorization->balanceCents;
        }

        return $balances;
    }
}
