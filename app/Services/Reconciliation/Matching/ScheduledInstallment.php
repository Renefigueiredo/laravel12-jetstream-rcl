<?php

namespace App\Services\Reconciliation\Matching;

use App\Enums\InstallmentStatus;

final readonly class ScheduledInstallment
{
    /**
     * One instalment foreseen for an authorization, and the payment that took it, if any.
     *
     * @param  string|null  $expectedMonth  First day of the month it is expected in, as Y-m-d
     */
    public function __construct(
        public int $position,
        public int $amountCents,
        public ?string $expectedMonth,
        public InstallmentStatus $status,
        public ?int $paymentId = null,
    ) {}
}
