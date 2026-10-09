<?php

namespace App\Services\Reconciliation\Matching;

use App\Enums\MatchClassification;

final readonly class MatchedPair
{
    /**
     * A pair evaluated by the matcher: linked on its own, or left as a suggestion.
     *
     * @param  int  $position  Rank among the suggestions of the authorization; ties share a position
     */
    public function __construct(
        public int $authorizationId,
        public int $paymentId,
        public PairScore $score,
        public MatchClassification $classification,
        public bool $paidBeforeAuthorization = false,
        public bool $cardMismatch = false,
        public bool $isTie = false,
        public int $position = 1,
    ) {}
}
