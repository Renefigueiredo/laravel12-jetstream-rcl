<?php

namespace App\Services\Reconciliation\Matching;

final readonly class EngineParameters
{
    /**
     * @param  int  $toleranceCents  Fixed tolerance
     * @param  int|null  $toleranceBasisPoints  Percentage tolerance in basis points (1% = 100)
     * @param  int|null  $toleranceCapCents  Most the percentage tolerance may reach
     * @param  int  $surchargeCapBasisPoints  Largest accepted surcharge over the authorized amount
     */
    public function __construct(
        public int $toleranceCents = 50,
        public ?int $toleranceBasisPoints = null,
        public int $surchargeCapBasisPoints = 1000,
        public int $automaticThreshold = 90,
        public int $suggestionThreshold = 60,
        public int $supplierThreshold = 90,
        public int $lookbackMonths = 3,
        public int $suggestionsPerAuthorization = 5,
        public string $cardMethodMarker = 'CARTAO',
        public ?int $toleranceCapCents = null,
    ) {}

    /**
     * The difference still treated as "the same amount" for a balance: the fixed amount, or the
     * percentage of the balance limited to its cap, whichever is larger.
     */
    public function toleranceFor(int $balanceCents): int
    {
        $percentage = $this->toleranceBasisPoints === null
            ? 0
            : intdiv(max(0, $balanceCents) * $this->toleranceBasisPoints, 10000);

        if ($this->toleranceCapCents !== null) {
            $percentage = min($percentage, $this->toleranceCapCents);
        }

        return max($this->toleranceCents, $percentage);
    }

    /**
     * Whether an amount paid above the authorized one may be accepted as a surcharge.
     */
    public function allowsSurcharge(int $excessCents, int $authorizedCents): bool
    {
        return $excessCents > 0 && $excessCents * 10000 <= $this->surchargeCapBasisPoints * $authorizedCents;
    }
}
