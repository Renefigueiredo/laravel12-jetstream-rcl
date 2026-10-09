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
     * What the imported payments say about a code: the name the ERP gives it and how many of
     * them carry it. The name is the one most payments use.
     *
     * @return array{name: string|null, payments: int}
     */
    public function usageOf(string $code): array
    {
        $names = PaymentEntry::query()
            ->where('operation_code', $code)
            ->toBase()
            ->selectRaw('operation_name, COUNT(*) as payments')
            ->groupBy('operation_name')
            ->orderByDesc('payments')
            ->orderBy('operation_name')
            ->get();

        return [
            'name' => $names->first()?->operation_name,
            'payments' => (int) $names->sum('payments'),
        ];
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
