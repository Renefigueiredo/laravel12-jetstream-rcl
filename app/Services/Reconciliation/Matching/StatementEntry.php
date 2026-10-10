<?php

namespace App\Services\Reconciliation\Matching;

use App\Enums\DifferenceTreatment;

final readonly class StatementEntry
{
    /**
     * What one link did to an authorization.
     *
     * @param  string  $paidOn  Date of the payment as Y-m-d
     * @param  int  $discountCents  What was written off as a discount when the link was closed
     * @param  int  $writeoffCents  What was written off for fitting the tolerance
     * @param  int  $excessCents  What the payment went over the balance, beyond the tolerance
     */
    public function __construct(
        public int $linkId,
        public int $paymentId,
        public string $paidOn,
        public int $paymentCents,
        public int $discountCents = 0,
        public int $writeoffCents = 0,
        public int $excessCents = 0,
        public ?DifferenceTreatment $treatment = null,
    ) {}
}
