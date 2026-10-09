<?php

namespace App\Services\Reconciliation;

use App\Enums\AuthorizationStatus;
use App\Enums\DifferenceTreatment;
use App\Enums\ImportFileStatus;
use App\Enums\LinkOrigin;
use App\Enums\SkipReason;
use App\Enums\SuggestionStatus;
use App\Models\AuthorizationEntry;
use App\Models\PaymentEntry;
use App\Models\PendingItem;
use App\Models\ReconciliationLink;
use App\Models\ReconciliationRun;
use App\Models\ReconciliationSkip;
use App\Models\ReconciliationSuggestion;

class RunTotals
{
    /**
     * Pairs accepted as the same amount with a difference above the fixed tolerance.
     *
     * @return array{count: int, paid_less_cents: int, paid_more_cents: int}
     */
    protected function differences(ReconciliationRun $run): array
    {
        $sums = ReconciliationLink::query()
            ->where('reconciliation_run_id', $run->id)
            ->whereNull('treatment')
            ->whereRaw('ABS(difference_cents) > ?', [$run->tolerance_cents])
            ->toBase()
            ->selectRaw('COUNT(*) as items')
            ->selectRaw('COALESCE(SUM(CASE WHEN difference_cents < 0 THEN -difference_cents ELSE 0 END), 0) as paid_less')
            ->selectRaw('COALESCE(SUM(CASE WHEN difference_cents > 0 THEN difference_cents ELSE 0 END), 0) as paid_more')
            ->first();

        return [
            'count' => (int) $sums->items,
            'paid_less_cents' => (int) $sums->paid_less,
            'paid_more_cents' => (int) $sums->paid_more,
        ];
    }

    /**
     * Totals of a run as they are now; every figure is counted or summed by the database.
     *
     * @return array<string, mixed>
     */
    public function for(ReconciliationRun $run): array
    {
        $sessionId = $run->reconciliation_session_id;

        $authorizations = AuthorizationEntry::query()
            ->leftJoin('import_files', 'import_files.id', '=', 'authorization_entries.import_file_id')
            ->where('authorization_entries.reconciliation_session_id', $sessionId)
            ->where(fn ($query) => $query
                ->whereNull('authorization_entries.import_file_id')
                ->orWhere('import_files.status', ImportFileStatus::Active))
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('reconciliation_skips')
                ->whereColumn('reconciliation_skips.authorization_entry_id', 'authorization_entries.id'));

        $authorizationsTotal = (clone $authorizations)->count();

        $reconciled = (clone $authorizations)
            ->join('authorization_states', 'authorization_states.authorization_entry_id', '=', 'authorization_entries.id')
            ->where('authorization_states.status', AuthorizationStatus::Reconciled);

        $withDecision = fn ($query) => $query
            ->selectRaw('1')
            ->from('reconciliation_links')
            ->whereColumn('reconciliation_links.authorization_entry_id', 'authorization_entries.id')
            ->whereNotNull('reconciliation_links.decided_by');

        $automatic = (clone $reconciled)->whereNotExists($withDecision)->count();
        $manual = (clone $reconciled)->whereExists($withDecision)->count();

        $pending = PendingItem::query()
            ->where('reconciliation_session_id', $sessionId)
            ->toBase()
            ->selectRaw('classification, COUNT(*) as items')
            ->groupBy('classification')
            ->pluck('items', 'classification');

        $links = ReconciliationLink::query()->where('reconciliation_run_id', $run->id);

        $treatments = [];

        foreach ([DifferenceTreatment::Discount, DifferenceTreatment::AcceptedSurcharge, DifferenceTreatment::Overpayment] as $treatment) {
            $sums = (clone $links)
                ->where('treatment', $treatment)
                ->toBase()
                ->selectRaw('COUNT(*) as items, COALESCE(SUM(discount_cents), 0) as discount_cents, COALESCE(SUM(excess_cents), 0) as excess_cents')
                ->first();

            $treatments[$treatment->value] = [
                'count' => (int) $sums->items,
                'cents' => (int) ($treatment === DifferenceTreatment::Discount ? $sums->discount_cents : $sums->excess_cents),
            ];
        }

        $excludedByCode = ReconciliationSkip::query()
            ->where('reconciliation_run_id', $run->id)
            ->where('reason', SkipReason::ExcludedCode)
            ->toBase()
            ->selectRaw('operation_code, COUNT(*) as items')
            ->groupBy('operation_code')
            ->orderBy('operation_code')
            ->pluck('items', 'operation_code')
            ->map(fn ($items): int => (int) $items)
            ->all();

        $paymentsTotal = PaymentEntry::query()
            ->join('import_files', 'import_files.id', '=', 'payment_entries.import_file_id')
            ->where('payment_entries.reconciliation_session_id', $sessionId)
            ->where('import_files.status', ImportFileStatus::Active)
            ->count();

        $excludedTotal = array_sum($excludedByCode);
        $duplicates = ReconciliationSkip::query()
            ->where('reconciliation_run_id', $run->id)
            ->where('reason', SkipReason::DuplicateOfOtherPeriod)
            ->count();

        $doubtful = (int) ($pending['doubtful'] ?? 0);
        $partial = (int) ($pending['partial'] ?? 0);
        $excess = (int) ($pending['excess'] ?? 0);

        return [
            'authorizations' => $authorizationsTotal,
            'reconciled_automatically' => $automatic,
            'reconciled_manually' => $manual,
            'automatic_percent' => $authorizationsTotal === 0 ? 0 : intdiv($automatic * 100, $authorizationsTotal),
            'doubtful' => $doubtful,
            'partial' => $partial,
            'excess' => $excess,
            'unmatched_authorizations' => (int) ($pending['unmatched_authorization'] ?? 0),
            'open_balance' => (int) ($pending['open_balance'] ?? 0),
            'unmatched_payments' => (int) ($pending['unmatched_payment'] ?? 0),
            'awaiting_decision' => $doubtful + $partial + $excess,
            'links_automatic' => (clone $links)->where('origin', LinkOrigin::Automatic)->whereNull('decided_by')->count(),
            'links_manual' => (clone $links)->whereNotNull('decided_by')->count(),
            'installments' => (clone $links)->where('is_installment', true)->count(),
            'prior_authorizations_linked' => (clone $links)
                ->join('authorization_entries', 'authorization_entries.id', '=', 'reconciliation_links.authorization_entry_id')
                ->where('authorization_entries.reconciliation_session_id', '<>', $sessionId)
                ->count(),
            'paid_before_authorization' => (clone $links)->where('paid_before_authorization', true)->count()
                + ReconciliationSuggestion::query()
                    ->where('reconciliation_run_id', $run->id)
                    ->where('status', SuggestionStatus::Pending)
                    ->where('paid_before_authorization', true)
                    ->count(),
            'with_difference' => $this->differences($run),
            'treatments' => $treatments,
            'payments' => $paymentsTotal,
            'payments_compared' => $paymentsTotal - $excludedTotal - $duplicates,
            'excluded_by_code' => $excludedByCode,
            'excluded_payments' => $excludedTotal,
            'duplicates' => $duplicates,
        ];
    }
}
