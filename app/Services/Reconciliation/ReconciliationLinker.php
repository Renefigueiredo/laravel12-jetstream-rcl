<?php

namespace App\Services\Reconciliation;

use App\Actions\Conciliation\ActionRefusedException;
use App\Enums\AuthorizationStatus;
use App\Enums\DifferenceTreatment;
use App\Enums\DifferenceType;
use App\Enums\JustificationCategory;
use App\Enums\MatchClassification;
use App\Enums\SuggestionStatus;
use App\Models\AuthorizationEntry;
use App\Models\PaymentEntry;
use App\Models\ReconciliationLink;
use App\Models\ReconciliationPairBlock;
use App\Models\ReconciliationSuggestion;
use App\Services\Reconciliation\Matching\EngineParameters;
use App\Services\Reconciliation\Matching\PairScorer;
use LogicException;

class ReconciliationLinker
{
    public function __construct(
        protected AuthorizationStateCalculator $states,
        protected PairScorer $scorer,
    ) {}

    /**
     * Link a payment to an authorization and derive the balance again.
     *
     * Every link, from the engine or from a user, is created here.
     *
     * @throws ActionRefusedException
     */
    public function link(AuthorizationEntry $authorization, PaymentEntry $payment, LinkAttributes $attributes): ReconciliationLink
    {
        $this->requireTransaction($authorization);

        $authorization = AuthorizationEntry::query()->lockForUpdate()->findOrFail($authorization->id);

        if (ReconciliationLink::query()->where('payment_entry_id', $payment->id)->exists()) {
            throw new ActionRefusedException(__('conciliation.reconciliation.errors.payment_already_linked'));
        }

        $balance = $authorization->state()->value('balance_cents') ?? $authorization->amount_cents;
        $difference = $payment->amount_cents - $balance;
        $withinTolerance = abs($difference) <= $attributes->parameters->toleranceFor($balance);

        $type = match (true) {
            $withinTolerance => DifferenceType::Exact,
            $difference < 0 => DifferenceType::Partial,
            default => DifferenceType::Excess,
        };

        $remaining = max(0, -$difference);
        $writeoff = $type === DifferenceType::Exact ? $remaining : 0;
        $discount = $type === DifferenceType::Partial && $attributes->treatment === DifferenceTreatment::Discount ? $remaining : 0;
        $category = $attributes->justificationCategory ?? ($writeoff > 0 ? JustificationCategory::WithinTolerance : null);

        $link = ReconciliationLink::query()->create([
            'authorization_entry_id' => $authorization->id,
            'payment_entry_id' => $payment->id,
            'reconciliation_run_id' => $attributes->run->id,
            'origin' => $attributes->origin,
            'is_installment' => $attributes->isInstallment,
            'engine_classification' => $attributes->engineClassification,
            'score' => $attributes->score,
            'supplier_score' => $attributes->supplierScore,
            'amount_score' => $attributes->amountScore,
            'difference_type' => $type,
            'difference_cents' => $difference,
            'excess_cents' => $type === DifferenceType::Excess ? $difference : 0,
            'treatment' => $type === DifferenceType::Exact ? null : $attributes->treatment,
            'discount_cents' => $discount,
            'tolerance_writeoff_cents' => $writeoff,
            'surcharge_cap_basis_points' => $attributes->treatment === DifferenceTreatment::AcceptedSurcharge
                ? $attributes->parameters->surchargeCapBasisPoints
                : null,
            'justification_category' => $category,
            'justification' => $attributes->justification,
            'paid_before_authorization' => $attributes->paidBeforeAuthorization,
            'card_mismatch' => $attributes->cardMismatch,
            'decided_by' => $attributes->decidedBy?->id,
            'decided_at' => $attributes->decidedBy === null ? null : now(),
        ]);

        $state = $this->states->recalculate($authorization->id);

        ReconciliationSuggestion::query()
            ->where('payment_entry_id', $payment->id)
            ->where('status', SuggestionStatus::Pending)
            ->update(['status' => SuggestionStatus::Superseded, 'updated_at' => now()]);

        $this->reassessSuggestions($authorization, $state?->balance_cents ?? 0, $attributes->parameters);

        return $link;
    }

    /**
     * Undo a link and derive the balance again; suggestions it had put aside come back.
     */
    public function unlink(ReconciliationLink $link, EngineParameters $parameters): void
    {
        $this->requireTransaction($link);

        $authorization = AuthorizationEntry::query()->lockForUpdate()->findOrFail($link->authorization_entry_id);
        $paymentId = $link->payment_entry_id;

        $link->delete();

        ReconciliationLink::query()
            ->where('authorization_entry_id', $authorization->id)
            ->where('tolerance_writeoff_cents', '>', 0)
            ->update(['tolerance_writeoff_cents' => 0, 'updated_at' => now()]);

        $state = $this->states->recalculate($authorization->id);
        $balance = $state?->balance_cents ?? $authorization->amount_cents;

        $this->restoreSuggestions($authorization, $paymentId);
        $this->reassessSuggestions($authorization, $balance, $parameters);
    }

    /**
     * Derive the balance again after a link of the authorization was changed in place.
     */
    public function refresh(AuthorizationEntry $authorization, EngineParameters $parameters): void
    {
        $this->requireTransaction($authorization);

        $state = $this->states->recalculate($authorization->id);

        $this->reassessSuggestions($authorization, $state?->balance_cents ?? $authorization->amount_cents, $parameters);
    }

    /**
     * Pending suggestions of the authorization are compared again with what is left to pay.
     *
     * Once part of it is paid, a payment larger than what is left is no longer a candidate:
     * suggesting R$ 300,00 for a balance of R$ 5,30 only adds noise.
     */
    protected function reassessSuggestions(AuthorizationEntry $authorization, int $balance, EngineParameters $parameters): void
    {
        $pending = ReconciliationSuggestion::query()
            ->where('authorization_entry_id', $authorization->id)
            ->where('status', SuggestionStatus::Pending)
            ->with('payment:id,amount_cents')
            ->get();

        $partlyPaid = ReconciliationLink::query()->where('authorization_entry_id', $authorization->id)->exists();

        foreach ($pending as $suggestion) {
            if ($balance === 0) {
                $suggestion->update(['status' => SuggestionStatus::Superseded]);

                continue;
            }

            $score = $this->scorer->score($suggestion->supplier_score, $balance, $suggestion->payment->amount_cents, $parameters);

            $classification = match ($score->classification) {
                MatchClassification::Automatic => MatchClassification::Doubtful,
                null => $score->withinTolerance ? MatchClassification::Doubtful : ($score->differenceCents < 0 ? MatchClassification::Partial : MatchClassification::Excess),
                default => $score->classification,
            };

            if ($partlyPaid && $classification === MatchClassification::Excess) {
                $suggestion->update(['status' => SuggestionStatus::Superseded]);

                continue;
            }

            $suggestion->update([
                'classification' => $classification,
                'score' => $score->score,
                'amount_score' => $score->amountScore,
                'difference_cents' => $score->differenceCents,
            ]);
        }
    }

    /**
     * Suggestions put aside by the removed link return when both ends are free and the pair is not blocked.
     */
    protected function restoreSuggestions(AuthorizationEntry $authorization, int $paymentId): void
    {
        $candidates = ReconciliationSuggestion::query()
            ->where('status', SuggestionStatus::Superseded)
            ->where(fn ($query) => $query
                ->where('authorization_entry_id', $authorization->id)
                ->orWhere('payment_entry_id', $paymentId))
            ->whereHas('run', fn ($query) => $query->where('status', 'completed'))
            ->with(['authorization.state', 'payment'])
            ->get();

        foreach ($candidates as $suggestion) {
            $free = $suggestion->authorization->status() !== AuthorizationStatus::Reconciled
                && ! ReconciliationLink::query()->where('payment_entry_id', $suggestion->payment_entry_id)->exists();

            $blocked = ReconciliationPairBlock::query()
                ->where('authorization_identity_key', $suggestion->authorization->identity_key)
                ->where('payment_unit', $suggestion->payment->unit->value)
                ->where('payment_identity_key', $suggestion->payment->identity_key)
                ->exists();

            if ($free && ! $blocked) {
                $suggestion->update(['status' => SuggestionStatus::Pending]);
            }
        }
    }

    protected function requireTransaction(AuthorizationEntry|ReconciliationLink $model): void
    {
        if ($model->getConnection()->transactionLevel() === 0) {
            throw new LogicException('Links must be created and removed inside a transaction.');
        }
    }
}
