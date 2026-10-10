<?php

namespace App\Services\Reconciliation\Matching;

final readonly class AuthorizationCandidate
{
    /**
     * @param  string  $supplier  Normalized supplier name
     * @param  int  $balanceCents  What is still to be paid
     * @param  string  $authorizedOn  Date as Y-m-d
     * @param  bool  $paysByCard  Whether the informed payment method is a card
     * @param  int|null  $installments  Instalments foreseen by the payment condition, when recognised
     * @param  list<int>  $installmentAmounts  Amounts of payments already linked as "still owed"
     * @param  list<int>|null  $planAmounts  Amounts of the instalments still open in the plan the operator informed; null without a plan
     */
    public function __construct(
        public int $id,
        public string $supplier,
        public int $balanceCents,
        public int $authorizedCents,
        public string $authorizedOn,
        public string $identityKey,
        public bool $paysByCard = false,
        public ?string $card = null,
        public ?int $installments = null,
        public array $installmentAmounts = [],
        public ?array $planAmounts = null,
    ) {}
}
