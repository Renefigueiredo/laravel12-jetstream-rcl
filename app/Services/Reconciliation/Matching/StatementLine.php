<?php

namespace App\Services\Reconciliation\Matching;

use App\Enums\StatementLineKind;

final readonly class StatementLine
{
    /**
     * @param  int  $amountCents  What the line takes from the balance; zero for a line that only informs
     * @param  int  $informedCents  The amount shown on the line
     * @param  int  $balanceAfterCents  What is left to pay after the line
     */
    public function __construct(
        public StatementLineKind $kind,
        public int $linkId,
        public int $paymentId,
        public string $paidOn,
        public int $amountCents,
        public int $informedCents,
        public int $balanceAfterCents,
    ) {}
}
