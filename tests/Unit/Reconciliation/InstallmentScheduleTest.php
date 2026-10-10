<?php

namespace Tests\Unit\Reconciliation;

use App\Enums\InstallmentStatus;
use App\Services\Reconciliation\Matching\EngineParameters;
use App\Services\Reconciliation\Matching\InstallmentSchedule;
use App\Services\Reconciliation\Matching\PaymentConditionParser;
use App\Services\Reconciliation\Matching\ScheduledInstallment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InstallmentScheduleTest extends TestCase
{
    /**
     * @param  list<int>|null  $plan
     * @param  list<int|array{0: int, 1: string}>  $payments  Amounts, or amount and date
     * @param  array<int, string|null>  $months  Expected month of each instalment of the plan
     * @return list<ScheduledInstallment>
     */
    protected function schedule(int $authorized, ?string $condition, ?array $plan = null, array $payments = [], ?string $latest = null, string $authorizedOn = '2026-07-05', array $months = []): array
    {
        return (new InstallmentSchedule(new PaymentConditionParser))->for(
            $authorized,
            $authorizedOn,
            $condition,
            $plan === null ? null : array_map(
                fn (int $cents, int $index): array => ['amount_cents' => $cents, 'expected_month' => $months[$index] ?? null],
                $plan,
                array_keys($plan),
            ),
            array_map(fn (int|array $payment, int $index): array => [
                'id' => $index + 1,
                'amount_cents' => is_array($payment) ? $payment[0] : $payment,
                'paid_on' => is_array($payment) ? $payment[1] : '2026-07-'.str_pad((string) (10 + $index), 2, '0', STR_PAD_LEFT),
            ], $payments, array_keys($payments)),
            $latest,
            new EngineParameters,
        );
    }

    /**
     * @param  list<ScheduledInstallment>  $installments
     * @return list<int>
     */
    protected function amounts(array $installments): array
    {
        return array_map(fn (ScheduledInstallment $installment): int => $installment->amountCents, $installments);
    }

    /**
     * @param  list<ScheduledInstallment>  $installments
     * @return list<int|null>
     */
    protected function takenBy(array $installments): array
    {
        return array_map(fn (ScheduledInstallment $installment): ?int => $installment->paymentId, $installments);
    }

    public function test_instalments_come_from_the_plan_then_from_the_condition(): void
    {
        $this->assertSame([30000, 30000, 30000], $this->amounts($this->schedule(90000, '3x')));
        $this->assertSame([33333, 33333, 33334], $this->amounts($this->schedule(100000, '3x')));
        $this->assertSame([40000, 30000, 30000], $this->amounts($this->schedule(100000, null, [40000, 30000, 30000])));
        $this->assertSame([40000, 30000, 30000], $this->amounts($this->schedule(100000, '3x', [40000, 30000, 30000])));
        $this->assertSame([], $this->schedule(100000, 'A vista'));
        $this->assertSame([], $this->schedule(100000, '488,02'));
        $this->assertSame([], $this->schedule(100000, null));
        $this->assertSame([1, 2, 3], array_map(fn (ScheduledInstallment $installment): int => $installment->position, $this->schedule(90000, '3x')));
    }

    /**
     * @param  list<int>  $payments
     * @param  list<int|null>  $takenBy
     */
    #[DataProvider('occupations')]
    public function test_each_payment_takes_the_first_free_instalment_of_its_amount(array $plan, array $payments, array $takenBy): void
    {
        $this->assertSame($takenBy, $this->takenBy($this->schedule(array_sum($plan), null, $plan, $payments)));
    }

    /**
     * @return array<string, array{0: list<int>, 1: list<int>, 2: list<int|null>}>
     */
    public static function occupations(): array
    {
        return [
            'a entrada' => [[40000, 30000, 30000], [40000], [1, null, null]],
            'uma parcela antes da entrada' => [[40000, 30000, 30000], [30000], [null, 1, null]],
            'todas, fora de ordem' => [[40000, 30000, 30000], [30000, 30000, 40000], [3, 1, 2]],
            'segunda cobrança da entrada fica fora' => [[40000, 30000, 30000], [40000, 40000], [1, null, null]],
            'valor que não é parcela fica fora' => [[40000, 30000, 30000], [25000], [null, null, null]],
            'centavo de resto' => [[33333, 33333, 33334], [33334], [1, null, null]],
            'no limite da tolerância' => [[30000, 30000, 30000], [30050], [1, null, null]],
            'acima da tolerância' => [[30000, 30000, 30000], [30051], [null, null, null]],
        ];
    }

    public function test_payments_take_instalments_in_date_order_then_by_identifier(): void
    {
        $installments = $this->schedule(60000, null, [30000, 30000], [[30000, '2026-08-10'], [30000, '2026-07-10'], [30000, '2026-07-10']]);

        $this->assertSame([2, 3], $this->takenBy($installments));
    }

    public function test_open_amounts_repeat_and_positions_point_to_payments(): void
    {
        $schedule = new InstallmentSchedule(new PaymentConditionParser);
        $installments = $this->schedule(100000, null, [40000, 30000, 30000], [40000]);

        $this->assertSame([30000, 30000], $schedule->openAmounts($installments));
        $this->assertSame([1 => 1], $schedule->positions($installments));
        $this->assertSame([], $schedule->openAmounts($this->schedule(60000, null, [30000, 30000], [30000, 30000])));
    }

    public function test_expected_months(): void
    {
        $months = fn (array $installments): array => array_map(fn (ScheduledInstallment $installment): ?string => $installment->expectedMonth, $installments);

        $this->assertSame(['2026-08-01', '2026-09-01', '2026-10-01'], $months($this->schedule(90000, '30/60/90 dias')));
        $this->assertSame(['2026-08-01', '2026-09-01'], $months($this->schedule(60000, '30/60 dias', authorizedOn: '2026-07-28')));
        $this->assertSame(['2026-08-01', '2026-09-01', '2026-10-01'], $months($this->schedule(90000, '3x')));
        $this->assertSame(['2026-08-01', '2026-09-01'], $months($this->schedule(60000, null, [30000, 30000])));
        $this->assertSame(['2026-07-01', '2026-12-01', '2026-10-01'], $months($this->schedule(90000, null, [30000, 30000, 30000], months: ['2026-07-01', '2026-12-15', null])));
        $this->assertSame(['2027-01-01', '2027-02-01'], $months($this->schedule(60000, '2x', authorizedOn: '2026-12-31')));
    }

    /**
     * @param  list<int|array{0: int, 1: string}>  $payments
     */
    #[DataProvider('delays')]
    public function test_an_open_instalment_is_overdue_once_its_month_was_processed(?string $latest, array $payments, InstallmentStatus $first): void
    {
        $installments = $this->schedule(60000, '2x', payments: $payments, latest: $latest);

        $this->assertSame('2026-08-01', $installments[0]->expectedMonth);
        $this->assertSame($first, $installments[0]->status);
    }

    /**
     * @return array<string, array{0: string|null, 1: list<array{0: int, 1: string}>, 2: InstallmentStatus}>
     */
    public static function delays(): array
    {
        return [
            'mês ainda não processado' => ['2026-07-01', [], InstallmentStatus::Open],
            'mês processado' => ['2026-08-01', [], InstallmentStatus::Overdue],
            'mês pulado, o seguinte processado' => ['2026-10-01', [], InstallmentStatus::Overdue],
            'paga depois do mês esperado' => ['2026-09-01', [[30000, '2026-09-10']], InstallmentStatus::Paid],
            'paga antes do mês esperado' => ['2026-09-01', [[30000, '2026-07-10']], InstallmentStatus::Paid],
            'nenhuma sessão processada' => [null, [], InstallmentStatus::Open],
        ];
    }

    public function test_only_the_instalments_already_due_are_overdue(): void
    {
        $installments = $this->schedule(90000, '30/60/90 dias', payments: [[30000, '2026-08-10']], latest: '2026-09-01');

        $this->assertSame(
            [InstallmentStatus::Paid, InstallmentStatus::Overdue, InstallmentStatus::Open],
            array_map(fn (ScheduledInstallment $installment): InstallmentStatus => $installment->status, $installments),
        );
    }
}
