<?php

namespace App\Services\Reconciliation\Matching;

use App\Enums\InstallmentStatus;
use DateTimeImmutable;

final class InstallmentSchedule
{
    public function __construct(private PaymentConditionParser $conditions) {}

    /**
     * The instalments foreseen for an authorization and which linked payment took each one.
     *
     * They come from the plan the operator informed or, without one, from a payment condition
     * with more than one instalment. Payments take instalments in date order: each one takes the
     * first free instalment whose amount it matches within the tolerance. Nothing here is
     * stored; the same input always gives the same schedule.
     *
     * @param  string  $authorizedOn  Y-m-d
     * @param  list<array{amount_cents: int, expected_month: string|null}>|null  $plan
     * @param  list<array{id: int, amount_cents: int, paid_on: string}>  $payments  Payments already linked
     * @param  string|null  $latestProcessedMonth  First day of the latest month with a processed session
     * @return list<ScheduledInstallment>
     */
    public function for(
        int $authorizedCents,
        string $authorizedOn,
        ?string $paymentCondition,
        ?array $plan,
        array $payments,
        ?string $latestProcessedMonth,
        EngineParameters $parameters,
    ): array {
        $foreseen = $plan !== null
            ? $this->fromPlan($plan, $authorizedOn)
            : $this->fromCondition($authorizedCents, $authorizedOn, $paymentCondition);

        usort($payments, fn (array $a, array $b): int => [$a['paid_on'], $a['id']] <=> [$b['paid_on'], $b['id']]);

        $takenBy = [];

        foreach ($payments as $payment) {
            foreach ($foreseen as $index => $installment) {
                if (! isset($takenBy[$index])
                    && abs($payment['amount_cents'] - $installment['amount_cents']) <= $parameters->toleranceFor($installment['amount_cents'])) {
                    $takenBy[$index] = $payment['id'];

                    break;
                }
            }
        }

        $installments = [];

        foreach ($foreseen as $index => $installment) {
            $paymentId = $takenBy[$index] ?? null;

            $installments[] = new ScheduledInstallment(
                position: $index + 1,
                amountCents: $installment['amount_cents'],
                expectedMonth: $installment['expected_month'],
                status: match (true) {
                    $paymentId !== null => InstallmentStatus::Paid,
                    $latestProcessedMonth !== null && $installment['expected_month'] !== null
                        && $installment['expected_month'] <= $latestProcessedMonth => InstallmentStatus::Overdue,
                    default => InstallmentStatus::Open,
                },
                paymentId: $paymentId,
            );
        }

        return $installments;
    }

    /**
     * Amounts of the instalments nobody paid yet, repeated as many times as they are foreseen.
     *
     * @param  list<ScheduledInstallment>  $installments
     * @return list<int>
     */
    public function openAmounts(array $installments): array
    {
        return array_values(array_map(
            fn (ScheduledInstallment $installment): int => $installment->amountCents,
            array_filter($installments, fn (ScheduledInstallment $installment): bool => $installment->status !== InstallmentStatus::Paid),
        ));
    }

    /**
     * @param  list<ScheduledInstallment>  $installments
     * @return array<int, int> Position of the instalment each payment took, by payment id
     */
    public function positions(array $installments): array
    {
        $positions = [];

        foreach ($installments as $installment) {
            if ($installment->paymentId !== null) {
                $positions[$installment->paymentId] = $installment->position;
            }
        }

        return $positions;
    }

    /**
     * @param  list<array{amount_cents: int, expected_month: string|null}>  $plan
     * @return list<array{amount_cents: int, expected_month: string}>
     */
    private function fromPlan(array $plan, string $authorizedOn): array
    {
        $foreseen = [];

        foreach (array_values($plan) as $index => $item) {
            $foreseen[] = [
                'amount_cents' => $item['amount_cents'],
                'expected_month' => $item['expected_month'] !== null
                    ? substr($item['expected_month'], 0, 7).'-01'
                    : $this->monthsAfter($authorizedOn, $index + 1),
            ];
        }

        return $foreseen;
    }

    /**
     * @return list<array{amount_cents: int, expected_month: string}>
     */
    private function fromCondition(int $authorizedCents, string $authorizedOn, ?string $paymentCondition): array
    {
        $count = $this->conditions->installments($paymentCondition);

        if ($count === null || $count < 2) {
            return [];
        }

        $termDays = $this->conditions->termDays($paymentCondition);
        $amount = intdiv($authorizedCents, $count);
        $foreseen = [];

        for ($index = 0; $index < $count; $index++) {
            $foreseen[] = [
                'amount_cents' => $index === $count - 1 ? $authorizedCents - $amount * ($count - 1) : $amount,
                'expected_month' => $termDays !== null
                    ? (new DateTimeImmutable($authorizedOn))->modify('+'.$termDays[$index].' days')->format('Y-m-01')
                    : $this->monthsAfter($authorizedOn, $index + 1),
            ];
        }

        return $foreseen;
    }

    private function monthsAfter(string $date, int $months): string
    {
        return (new DateTimeImmutable(substr($date, 0, 7).'-01'))->modify('+'.$months.' months')->format('Y-m-01');
    }
}
