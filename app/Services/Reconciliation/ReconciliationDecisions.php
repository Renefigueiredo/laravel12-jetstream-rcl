<?php

namespace App\Services\Reconciliation;

use App\Actions\Conciliation\ActionRefusedException;
use App\Enums\DifferenceTreatment;
use App\Enums\DifferenceType;
use App\Enums\ImportFileStatus;
use App\Enums\PairBlockReason;
use App\Enums\SessionStatus;
use App\Models\AuthorizationEntry;
use App\Models\PaymentEntry;
use App\Models\ReconciliationLink;
use App\Models\ReconciliationPairBlock;
use App\Models\ReconciliationRun;
use App\Models\ReconciliationSkip;
use App\Models\User;
use App\Services\Reconciliation\Matching\EngineParameters;

/**
 * Rules shared by every action an operator takes on the result of a run.
 */
class ReconciliationDecisions
{
    /**
     * The run in effect for the session of a payment; decisions exist only on processed sessions.
     *
     * @throws ActionRefusedException
     */
    public function runOf(PaymentEntry $payment): ReconciliationRun
    {
        $session = $payment->session()->with('currentRun')->firstOrFail();

        if ($session->status !== SessionStatus::Processed || $session->currentRun === null) {
            throw new ActionRefusedException(__('conciliation.reconciliation.errors.session_not_processed'));
        }

        return $session->currentRun;
    }

    /**
     * A payment can be linked when it belongs to an active file, was not left out of the run
     * and has no link yet.
     *
     * @throws ActionRefusedException
     */
    public function assertLinkable(PaymentEntry $payment): void
    {
        if ($payment->importFile->status !== ImportFileStatus::Active) {
            throw new ActionRefusedException(__('conciliation.reconciliation.errors.payment_not_active'));
        }

        if (ReconciliationSkip::query()->where('payment_entry_id', $payment->id)->exists()) {
            throw new ActionRefusedException(__('conciliation.reconciliation.errors.payment_skipped'));
        }

        if (ReconciliationLink::query()->where('payment_entry_id', $payment->id)->exists()) {
            throw new ActionRefusedException(__('conciliation.reconciliation.errors.payment_already_linked'));
        }
    }

    /**
     * How a payment compares with what is left to pay.
     *
     * @return array{0: DifferenceType, 1: int} The type and the signed difference in cents
     */
    public function compare(int $balanceCents, int $paymentCents, EngineParameters $parameters): array
    {
        $difference = $paymentCents - $balanceCents;

        $type = match (true) {
            abs($difference) <= $parameters->toleranceFor($balanceCents) => DifferenceType::Exact,
            $difference < 0 => DifferenceType::Partial,
            default => DifferenceType::Excess,
        };

        return [$type, $difference];
    }

    /**
     * A difference beyond the tolerance needs a decision that fits it.
     *
     * @throws ActionRefusedException
     */
    public function assertDecisionFits(DifferenceType $type, int $differenceCents, int $authorizedCents, ?DifferenceDecision $decision, EngineParameters $parameters): void
    {
        if ($type === DifferenceType::Exact) {
            return;
        }

        $allowed = $type === DifferenceType::Partial
            ? [DifferenceTreatment::StillOwed, DifferenceTreatment::Discount]
            : [DifferenceTreatment::Overpayment, DifferenceTreatment::AcceptedSurcharge];

        if ($decision === null || ! in_array($decision->treatment, $allowed, true)) {
            throw new ActionRefusedException(__('conciliation.reconciliation.errors.treatment_required.'.$type->value));
        }

        $needsJustification = in_array($decision->treatment, [DifferenceTreatment::Discount, DifferenceTreatment::AcceptedSurcharge], true);

        if ($needsJustification && ($decision->category === null || blank($decision->justification))) {
            throw new ActionRefusedException(__('conciliation.reconciliation.errors.justification_required'));
        }

        if ($needsJustification && mb_strlen((string) $decision->justification) > 500) {
            throw new ActionRefusedException(__('conciliation.reconciliation.errors.justification_too_long'));
        }

        if ($decision->treatment === DifferenceTreatment::AcceptedSurcharge && ! $parameters->allowsSurcharge($differenceCents, $authorizedCents)) {
            throw new ActionRefusedException(__('conciliation.reconciliation.errors.surcharge_above_cap', [
                'cap' => rtrim(rtrim(number_format($parameters->surchargeCapBasisPoints / 100, 2, ',', '.'), '0'), ','),
            ]));
        }
    }

    /**
     * The engine never suggests nor links this pair again; a person still may.
     */
    public function block(AuthorizationEntry $authorization, PaymentEntry $payment, PairBlockReason $reason, User $user): void
    {
        ReconciliationPairBlock::query()->firstOrCreate(
            [
                'authorization_identity_key' => $authorization->identity_key,
                'payment_unit' => $payment->unit->value,
                'payment_identity_key' => $payment->identity_key,
            ],
            ['reason' => $reason, 'created_by' => $user->id],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function describe(ReconciliationLink $link): array
    {
        return [
            'authorization_entry_id' => $link->authorization_entry_id,
            'payment_entry_id' => $link->payment_entry_id,
            'origin' => $link->origin->value,
            'is_installment' => $link->is_installment,
            'engine_classification' => $link->engine_classification?->value,
            'score' => $link->score,
            'supplier_score' => $link->supplier_score,
            'amount_score' => $link->amount_score,
            'difference_type' => $link->difference_type->value,
            'difference_cents' => $link->difference_cents,
            'treatment' => $link->treatment?->value,
            'discount_cents' => $link->discount_cents,
            'excess_cents' => $link->excess_cents,
            'justification_category' => $link->justification_category?->value,
            'justification' => $link->justification,
            'paid_before_authorization' => $link->paid_before_authorization,
            'card_mismatch' => $link->card_mismatch,
            'decided_by' => $link->decided_by,
        ];
    }
}
