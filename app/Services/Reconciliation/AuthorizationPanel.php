<?php

namespace App\Services\Reconciliation;

use App\Enums\DifferenceTreatment;
use App\Enums\ImportFileStatus;
use App\Enums\MatchClassification;
use App\Enums\PendingItemKind;
use App\Enums\SessionStatus;
use App\Models\AuthorizationEntry;
use App\Models\PendingItem;
use App\Models\ReconciliationLink;
use App\Models\ReconciliationSession;
use Illuminate\Database\Eloquent\Builder;

class AuthorizationPanel
{
    /**
     * The authorizations the panel shows, whatever session they came in: active, of a processed
     * session and not left out as a duplicate of another period.
     *
     * @return Builder<AuthorizationEntry>
     */
    public function query(): Builder
    {
        return AuthorizationEntry::query()
            ->whereIn('authorization_entries.reconciliation_session_id', ReconciliationSession::query()
                ->where('status', SessionStatus::Processed)
                ->select('id'))
            ->where(fn (Builder $query) => $query
                ->whereNull('authorization_entries.import_file_id')
                ->orWhereHas('importFile', fn (Builder $query) => $query->where('status', ImportFileStatus::Active)))
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('reconciliation_skips')
                ->whereColumn('reconciliation_skips.authorization_entry_id', 'authorization_entries.id'));
    }

    /**
     * @return Builder<AuthorizationEntry> Authorizations with something left to pay
     */
    public function open(): Builder
    {
        return $this->query()->whereDoesntHave('state', fn (Builder $query) => $query->where('balance_cents', 0));
    }

    /**
     * @return Builder<AuthorizationEntry> Authorizations with nothing left to pay
     */
    public function reconciled(): Builder
    {
        return $this->query()->whereHas('state', fn (Builder $query) => $query->where('balance_cents', 0));
    }

    /**
     * Totals of the authorizations of a query, summed by the database.
     *
     * @param  Builder<AuthorizationEntry>|null  $filtered  Defaults to every authorization of the panel
     * @return array{authorizations: int, authorized_cents: int, paid_cents: int, balance_cents: int, discount_cents: int, accepted_surcharge_cents: int, overpayment_cents: int, open: int, partial: int, reconciled: int}
     */
    public function totals(?Builder $filtered = null): array
    {
        $ids = ($filtered ?? $this->query())->clone()->reorder()->select('authorization_entries.id');

        $sums = AuthorizationEntry::query()
            ->leftJoin('authorization_states', 'authorization_states.authorization_entry_id', '=', 'authorization_entries.id')
            ->whereIn('authorization_entries.id', $ids)
            ->toBase()
            ->selectRaw('COUNT(*) as authorizations')
            ->selectRaw('COALESCE(SUM(authorization_entries.amount_cents), 0) as authorized_cents')
            ->selectRaw('COALESCE(SUM(authorization_states.paid_cents), 0) as paid_cents')
            ->selectRaw('COALESCE(SUM(COALESCE(authorization_states.balance_cents, authorization_entries.amount_cents)), 0) as balance_cents')
            ->selectRaw('COALESCE(SUM(authorization_states.discount_cents), 0) as discount_cents')
            ->selectRaw('SUM(CASE WHEN authorization_states.authorization_entry_id IS NULL THEN 1 ELSE 0 END) as open_count')
            ->selectRaw('SUM(CASE WHEN authorization_states.balance_cents > 0 THEN 1 ELSE 0 END) as partial_count')
            ->selectRaw('SUM(CASE WHEN authorization_states.balance_cents = 0 THEN 1 ELSE 0 END) as reconciled_count')
            ->first();

        $excess = ReconciliationLink::query()
            ->whereIn('authorization_entry_id', $ids)
            ->whereIn('treatment', [DifferenceTreatment::AcceptedSurcharge, DifferenceTreatment::Overpayment])
            ->toBase()
            ->selectRaw('treatment, COALESCE(SUM(excess_cents), 0) as cents')
            ->groupBy('treatment')
            ->pluck('cents', 'treatment');

        return [
            'authorizations' => (int) $sums->authorizations,
            'authorized_cents' => (int) $sums->authorized_cents,
            'paid_cents' => (int) $sums->paid_cents,
            'balance_cents' => (int) $sums->balance_cents,
            'discount_cents' => (int) $sums->discount_cents,
            'accepted_surcharge_cents' => (int) ($excess[DifferenceTreatment::AcceptedSurcharge->value] ?? 0),
            'overpayment_cents' => (int) ($excess[DifferenceTreatment::Overpayment->value] ?? 0),
            'open' => (int) $sums->open_count,
            'partial' => (int) $sums->partial_count,
            'reconciled' => (int) $sums->reconciled_count,
        ];
    }

    /**
     * What asks for attention, each figure counted by the database.
     *
     * @return array{overdue: int, overpaid: int, divergences: int}
     */
    public function alerts(): array
    {
        return [
            'overdue' => $this->overdue($this->open())->count(),
            'overpaid' => $this->overpaid($this->query())->count(),
            'divergences' => $this->divergences()->count(),
        ];
    }

    /**
     * Payments of processed sessions that found no authorization, or that only have a pending
     * suggestion above the balance of an authorization.
     *
     * @return Builder<PendingItem>
     */
    public function divergences(): Builder
    {
        return PendingItem::query()->where(fn (Builder $query) => $query
            ->where('kind', PendingItemKind::UnmatchedPayment)
            ->orWhere(fn (Builder $query) => $query
                ->where('kind', PendingItemKind::Suggestion)
                ->where('classification', MatchClassification::Excess->value)));
    }

    /**
     * @param  Builder<AuthorizationEntry>  $query
     * @return Builder<AuthorizationEntry> Authorizations with an instalment that should have been paid by now
     */
    public function overdue(Builder $query): Builder
    {
        return $query->whereHas('forecast', fn (Builder $query) => $query->where('overdue_count', '>', 0));
    }

    /**
     * @param  Builder<AuthorizationEntry>  $query
     * @return Builder<AuthorizationEntry> Authorizations with a payment recorded as paid in excess
     */
    public function overpaid(Builder $query): Builder
    {
        return $query->whereHas('links', fn (Builder $query) => $query->where('treatment', DifferenceTreatment::Overpayment));
    }

    public function hasProcessedSession(): bool
    {
        return ReconciliationSession::query()->where('status', SessionStatus::Processed)->exists();
    }
}
