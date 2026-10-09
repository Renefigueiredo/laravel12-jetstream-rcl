<?php

namespace App\Services\Reconciliation\Matching;

final readonly class PaymentCandidate
{
    /**
     * @param  string  $supplier  Normalized supplier name
     * @param  string  $paidOn  Date as Y-m-d
     * @param  string|null  $card  Card of the invoice the payment came from, when it did
     */
    public function __construct(
        public int $id,
        public string $supplier,
        public int $amountCents,
        public string $paidOn,
        public string $unit,
        public string $identityKey,
        public ?string $card = null,
    ) {}
}
