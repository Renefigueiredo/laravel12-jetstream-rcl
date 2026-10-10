<?php

namespace Tests\Unit\Reconciliation;

use App\Enums\DifferenceTreatment;
use App\Enums\StatementLineKind;
use App\Services\Reconciliation\Matching\AuthorizationStatement;
use App\Services\Reconciliation\Matching\StatementEntry;
use App\Services\Reconciliation\Matching\StatementLine;
use PHPUnit\Framework\TestCase;

class AuthorizationStatementTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $extra
     */
    protected function entry(int $id, string $paidOn, int $cents, array $extra = []): StatementEntry
    {
        return new StatementEntry(...['linkId' => $id, 'paymentId' => $id, 'paidOn' => $paidOn, 'paymentCents' => $cents, ...$extra]);
    }

    /**
     * @param  list<StatementLine>  $lines
     * @return list<array{0: string, 1: int, 2: int}>
     */
    protected function summary(array $lines): array
    {
        return array_map(fn (StatementLine $line): array => [$line->kind->value, $line->informedCents, $line->balanceAfterCents], $lines);
    }

    public function test_each_payment_shows_what_was_left_after_it(): void
    {
        $lines = (new AuthorizationStatement)->lines(90000, [
            $this->entry(2, '2026-08-10', 30000, ['treatment' => DifferenceTreatment::StillOwed]),
            $this->entry(1, '2026-07-10', 30000, ['treatment' => DifferenceTreatment::StillOwed]),
        ]);

        $this->assertSame([['payment', 30000, 60000], ['payment', 30000, 30000]], $this->summary($lines));
        $this->assertSame([1, 2], array_map(fn (StatementLine $line): int => $line->linkId, $lines));
        $this->assertSame(30000, (new AuthorizationStatement)->balance(90000, $lines));
    }

    public function test_instalments_with_a_cent_left_over_end_at_zero(): void
    {
        $lines = (new AuthorizationStatement)->lines(100000, [
            $this->entry(1, '2026-07-10', 33333),
            $this->entry(2, '2026-08-10', 33333),
            $this->entry(3, '2026-09-10', 33334),
        ]);

        $this->assertSame([66667, 33334, 0], array_map(fn (StatementLine $line): int => $line->balanceAfterCents, $lines));
    }

    public function test_payments_on_the_same_day_follow_the_identifier(): void
    {
        $lines = (new AuthorizationStatement)->lines(1000, [$this->entry(9, '2026-07-10', 400), $this->entry(3, '2026-07-10', 600)]);

        $this->assertSame([3, 9], array_map(fn (StatementLine $line): int => $line->paymentId, $lines));
    }

    public function test_writeoff_within_the_tolerance_is_a_line_of_its_own(): void
    {
        $lines = (new AuthorizationStatement)->lines(10000, [
            $this->entry(1, '2026-07-10', 5000, ['treatment' => DifferenceTreatment::StillOwed]),
            $this->entry(2, '2026-08-10', 4850, ['writeoffCents' => 150]),
        ]);

        $this->assertSame([
            ['payment', 5000, 5000],
            ['payment', 4850, 150],
            ['tolerance_writeoff', 150, 0],
        ], $this->summary($lines));
    }

    public function test_discount_is_a_line_of_its_own(): void
    {
        $lines = (new AuthorizationStatement)->lines(100000, [
            $this->entry(1, '2026-07-10', 90000, ['discountCents' => 10000, 'treatment' => DifferenceTreatment::Discount]),
        ]);

        $this->assertSame([['payment', 90000, 10000], ['discount', 10000, 0]], $this->summary($lines));
        $this->assertSame(10000, $lines[1]->amountCents);
    }

    public function test_surcharge_and_overpayment_only_inform_the_excess(): void
    {
        $statement = new AuthorizationStatement;

        $surcharge = $statement->lines(50000, [$this->entry(1, '2026-07-10', 52000, ['excessCents' => 2000, 'treatment' => DifferenceTreatment::AcceptedSurcharge])]);
        $overpayment = $statement->lines(50000, [$this->entry(1, '2026-07-10', 60000, ['excessCents' => 10000, 'treatment' => DifferenceTreatment::Overpayment])]);

        $this->assertSame([['payment', 52000, 0], ['accepted_surcharge', 2000, 0]], $this->summary($surcharge));
        $this->assertSame([['payment', 60000, 0], ['overpayment', 10000, 0]], $this->summary($overpayment));
        $this->assertSame(0, $surcharge[1]->amountCents);
        $this->assertSame(StatementLineKind::Overpayment, $overpayment[1]->kind);
    }

    public function test_payment_slightly_above_the_balance_within_the_tolerance_has_no_extra_line(): void
    {
        $lines = (new AuthorizationStatement)->lines(43000, [$this->entry(1, '2026-07-10', 43030)]);

        $this->assertSame([['payment', 43030, 0]], $this->summary($lines));
    }

    public function test_authorization_without_links_has_no_lines(): void
    {
        $statement = new AuthorizationStatement;

        $this->assertSame([], $statement->lines(90000, []));
        $this->assertSame(90000, $statement->balance(90000, []));
    }

    public function test_removing_a_payment_from_the_middle_changes_every_later_balance(): void
    {
        $statement = new AuthorizationStatement;
        $all = [$this->entry(1, '2026-07-10', 30000), $this->entry(2, '2026-08-10', 30000), $this->entry(3, '2026-09-10', 30000)];

        $this->assertSame([60000, 30000, 0], array_map(fn (StatementLine $line): int => $line->balanceAfterCents, $statement->lines(90000, $all)));
        $this->assertSame([60000, 30000], array_map(fn (StatementLine $line): int => $line->balanceAfterCents, $statement->lines(90000, [$all[0], $all[2]])));
    }
}
