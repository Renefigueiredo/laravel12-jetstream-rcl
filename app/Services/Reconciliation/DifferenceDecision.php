<?php

namespace App\Services\Reconciliation;

use App\Enums\DifferenceTreatment;
use App\Enums\JustificationCategory;

final readonly class DifferenceDecision
{
    /**
     * What the operator decided about a payment that differs from what is left to pay.
     */
    public function __construct(
        public DifferenceTreatment $treatment,
        public ?JustificationCategory $category = null,
        public ?string $justification = null,
    ) {}
}
