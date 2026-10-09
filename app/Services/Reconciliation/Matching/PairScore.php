<?php

namespace App\Services\Reconciliation\Matching;

use App\Enums\MatchClassification;

final readonly class PairScore
{
    /**
     * @param  int  $differenceCents  Payment minus the reference amount, signed
     * @param  MatchClassification|null  $classification  Null when the pair deserves no suggestion
     */
    public function __construct(
        public int $supplierScore,
        public int $amountScore,
        public int $score,
        public int $differenceCents,
        public bool $withinTolerance,
        public ?MatchClassification $classification,
    ) {}
}
