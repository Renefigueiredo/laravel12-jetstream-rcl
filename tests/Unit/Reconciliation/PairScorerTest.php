<?php

namespace Tests\Unit\Reconciliation;

use App\Enums\MatchClassification;
use App\Services\Reconciliation\Matching\EngineParameters;
use App\Services\Reconciliation\Matching\PairScorer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PairScorerTest extends TestCase
{
    #[DataProvider('pairs')]
    public function test_score_and_classification(int $balance, int $payment, ?int $percent, int $supplier, int $amount, int $score, ?MatchClassification $classification): void
    {
        $result = (new PairScorer)->score($supplier, $balance, $payment, new EngineParameters(toleranceCents: 50, toleranceBasisPoints: $percent));

        $this->assertSame($amount, $result->amountScore);
        $this->assertSame($score, $result->score);
        $this->assertSame($classification, $result->classification);
        $this->assertSame($payment - $balance, $result->differenceCents);
    }

    /**
     * @return array<string, array{int, int, int|null, int, int, int, MatchClassification|null}>
     */
    public static function pairs(): array
    {
        return [
            'identical' => [125040, 125040, null, 100, 100, 100, MatchClassification::Automatic],
            'cents apart' => [43000, 43030, null, 100, 100, 100, MatchClassification::Automatic],
            'at the tolerance' => [43000, 43050, null, 100, 100, 100, MatchClassification::Automatic],
            'one cent beyond, above' => [43000, 43051, null, 100, 99, 99, MatchClassification::Excess],
            'one cent beyond, below' => [43000, 42949, null, 100, 99, 99, MatchClassification::Partial],
            'within the percentage' => [100000, 100800, 100, 100, 100, 100, MatchClassification::Automatic],
            'beyond the percentage' => [100000, 101001, 100, 100, 98, 98, MatchClassification::Excess],
            'supplier at the automatic threshold' => [100000, 100000, null, 90, 100, 90, MatchClassification::Automatic],
            'supplier just below it' => [100000, 100000, null, 89, 100, 89, MatchClassification::Doubtful],
            'supplier at the suggestion threshold' => [100000, 100000, null, 60, 100, 60, MatchClassification::Doubtful],
            'supplier just below it, no suggestion' => [100000, 100000, null, 59, 100, 59, null],
            'paid a third' => [300000, 100000, null, 100, 33, 33, MatchClassification::Partial],
            'paid thirty percent more' => [50000, 65000, null, 100, 70, 70, MatchClassification::Excess],
            'different amount and weak supplier' => [300000, 100000, null, 89, 33, 33, null],
            'more than double' => [10000, 30000, null, 100, 0, 0, MatchClassification::Excess],
        ];
    }

    public function test_thresholds_come_from_the_parameters(): void
    {
        $strict = new EngineParameters(automaticThreshold: 95, suggestionThreshold: 80, supplierThreshold: 95);
        $scorer = new PairScorer;

        $this->assertSame(MatchClassification::Doubtful, $scorer->score(94, 100000, 100000, $strict)->classification);
        $this->assertSame(MatchClassification::Automatic, $scorer->score(95, 100000, 100000, $strict)->classification);
        $this->assertNull($scorer->score(79, 100000, 100000, $strict)->classification);
        $this->assertNull($scorer->score(94, 300000, 100000, $strict)->classification);
        $this->assertSame(MatchClassification::Partial, $scorer->score(95, 300000, 100000, $strict)->classification);
    }

    public function test_an_instalment_is_scored_against_its_reference_amount(): void
    {
        $scorer = new PairScorer;
        $parameters = new EngineParameters;

        $againstBalance = $scorer->score(100, 90000, 30000, $parameters);
        $againstInstalment = $scorer->score(100, 30000, 30000, $parameters);

        $this->assertSame(33, $againstBalance->score);
        $this->assertSame(100, $againstInstalment->score);
        $this->assertSame(MatchClassification::Automatic, $againstInstalment->classification);
        $this->assertSame(MatchClassification::Doubtful, $scorer->score(89, 30000, 30000, $parameters)->classification);
        $this->assertSame(MatchClassification::Excess, $scorer->score(100, 30000, 30051, $parameters)->classification);
    }

    public function test_zero_tolerance_accepts_only_the_exact_amount(): void
    {
        $parameters = new EngineParameters(toleranceCents: 0);
        $scorer = new PairScorer;

        $this->assertSame(MatchClassification::Automatic, $scorer->score(100, 100000, 100000, $parameters)->classification);
        $this->assertSame(MatchClassification::Excess, $scorer->score(100, 100000, 100001, $parameters)->classification);
    }
}
