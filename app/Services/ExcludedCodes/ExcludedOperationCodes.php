<?php

namespace App\Services\ExcludedCodes;

use App\Models\ExcludedOperationCode;
use App\Models\PaymentEntry;

class ExcludedOperationCodes
{
    /**
     * The list as it is now; later changes to the list do not change the snapshot.
     */
    public function snapshot(): ExcludedCodeSnapshot
    {
        return new ExcludedCodeSnapshot(ExcludedOperationCode::query()->toBase()->pluck('code'));
    }

    /**
     * Whether a code looks mistyped: payments were imported and none of them carries it.
     *
     * Nothing can be said while no payment was imported.
     */
    public function isUnusedByImportedPayments(string $code): bool
    {
        return PaymentEntry::query()->exists()
            && PaymentEntry::query()->where('operation_code', $code)->doesntExist();
    }
}
