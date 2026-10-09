<?php

namespace App\Services\Reconciliation;

use App\Enums\AuthorizationStatus;
use App\Models\AuthorizationEntry;
use App\Models\AuthorizationState;
use App\Models\ReconciliationLink;
use LogicException;

class AuthorizationStateCalculator
{
    /**
     * Derive the balance and the status of an authorization from its links.
     *
     * The authorization row is locked, so two actions on it are applied one at a time.
     */
    public function recalculate(int $authorizationEntryId): ?AuthorizationState
    {
        $authorization = AuthorizationEntry::query()->lockForUpdate()->find($authorizationEntryId);

        if ($authorization === null) {
            return null;
        }

        if ($authorization->getConnection()->transactionLevel() === 0) {
            throw new LogicException('Authorization states must be recalculated in the same transaction as the link change.');
        }

        $sums = ReconciliationLink::query()
            ->where('reconciliation_links.authorization_entry_id', $authorizationEntryId)
            ->join('payment_entries', 'payment_entries.id', '=', 'reconciliation_links.payment_entry_id')
            ->selectRaw('COUNT(*) as links_count')
            ->selectRaw('COALESCE(SUM(payment_entries.amount_cents), 0) as paid_cents')
            ->selectRaw('COALESCE(SUM(reconciliation_links.discount_cents), 0) as discount_cents')
            ->selectRaw('COALESCE(SUM(reconciliation_links.tolerance_writeoff_cents), 0) as writeoff_cents')
            ->toBase()
            ->first();

        $linksCount = (int) $sums->links_count;

        if ($linksCount === 0) {
            AuthorizationState::query()->whereKey($authorizationEntryId)->delete();

            return null;
        }

        $paid = (int) $sums->paid_cents;
        $discount = (int) $sums->discount_cents;
        $writeoff = (int) $sums->writeoff_cents;
        $balance = max(0, $authorization->amount_cents - $paid - $discount - $writeoff);

        return AuthorizationState::query()->updateOrCreate(
            ['authorization_entry_id' => $authorizationEntryId],
            [
                'links_count' => $linksCount,
                'paid_cents' => $paid,
                'discount_cents' => $discount,
                'writeoff_cents' => $writeoff,
                'balance_cents' => $balance,
                'status' => $balance === 0 ? AuthorizationStatus::Reconciled : AuthorizationStatus::Partial,
            ],
        );
    }
}
