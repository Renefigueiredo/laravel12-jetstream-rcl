<?php

namespace App\Services\Reconciliation;

use App\Enums\DifferenceTreatment;
use App\Enums\JustificationCategory;
use App\Enums\LinkOrigin;
use App\Enums\MatchClassification;
use App\Models\ReconciliationRun;
use App\Models\User;
use App\Services\Reconciliation\Matching\EngineParameters;

final readonly class LinkAttributes
{
    /**
     * @param  ReconciliationRun  $run  The run in effect for the session of the payment
     * @param  EngineParameters  $parameters  The parameters of that run
     * @param  User|null  $decidedBy  Who confirmed, created or treated the link; null for the engine
     */
    public function __construct(
        public ReconciliationRun $run,
        public EngineParameters $parameters,
        public LinkOrigin $origin,
        public bool $isInstallment = false,
        public ?MatchClassification $engineClassification = null,
        public ?int $score = null,
        public ?int $supplierScore = null,
        public ?int $amountScore = null,
        public ?DifferenceTreatment $treatment = null,
        public ?JustificationCategory $justificationCategory = null,
        public ?string $justification = null,
        public bool $paidBeforeAuthorization = false,
        public bool $cardMismatch = false,
        public ?User $decidedBy = null,
    ) {}
}
