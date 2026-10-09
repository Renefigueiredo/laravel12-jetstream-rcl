<?php

namespace App\Services\Reconciliation\Matching;

use App\Enums\MatchClassification;

final class PairScorer
{
    /**
     * Score a pair from its two axes; the score is the lower of them.
     *
     * @param  int  $referenceCents  The open balance, or the instalment the payment is compared with
     */
    public function score(int $supplierScore, int $referenceCents, int $paymentCents, EngineParameters $parameters): PairScore
    {
        $difference = $paymentCents - $referenceCents;
        $withinTolerance = abs($difference) <= $parameters->toleranceFor($referenceCents);
        $amountScore = $this->amountScore($difference, $referenceCents, $withinTolerance);
        $score = min($supplierScore, $amountScore);

        return new PairScore(
            supplierScore: $supplierScore,
            amountScore: $amountScore,
            score: $score,
            differenceCents: $difference,
            withinTolerance: $withinTolerance,
            classification: $this->classify($supplierScore, $score, $difference, $withinTolerance, $parameters),
        );
    }

    private function amountScore(int $difference, int $referenceCents, bool $withinTolerance): int
    {
        if ($withinTolerance) {
            return 100;
        }

        if ($referenceCents <= 0) {
            return 0;
        }

        return max(0, min(99, intdiv(($referenceCents - abs($difference)) * 100, $referenceCents)));
    }

    private function classify(int $supplierScore, int $score, int $difference, bool $withinTolerance, EngineParameters $parameters): ?MatchClassification
    {
        if ($withinTolerance) {
            return match (true) {
                $score >= $parameters->automaticThreshold => MatchClassification::Automatic,
                $score >= $parameters->suggestionThreshold => MatchClassification::Doubtful,
                default => null,
            };
        }

        if ($supplierScore < $parameters->supplierThreshold) {
            return null;
        }

        return $difference < 0 ? MatchClassification::Partial : MatchClassification::Excess;
    }
}
