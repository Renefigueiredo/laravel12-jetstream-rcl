<?php

namespace Tests\Unit\Reconciliation;

use App\Enums\MatchClassification;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\BuildsMatcherCandidates;

class MatcherInstallmentPlanTest extends TestCase
{
    use BuildsMatcherCandidates;

    public function test_a_payment_with_the_amount_of_an_open_instalment_of_the_plan_is_linked(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'GRAFICA SUL', 100000, ['planAmounts' => [40000, 30000, 30000]])],
            [$this->payment(10, 'GRAFICA SUL', 40000)],
        );

        $this->assertSame([[1, 10]], $this->ids($result->links));
        $this->assertSame(MatchClassification::Installment, $result->links[0]->classification);
        $this->assertSame(100, $result->links[0]->score->score);
    }

    public function test_an_amount_with_no_open_instalment_waits_for_the_operator(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'GRAFICA SUL', 100000, ['balanceCents' => 60000, 'planAmounts' => [30000, 30000]])],
            [$this->payment(10, 'GRAFICA SUL', 40000)],
        );

        $this->assertSame([], $result->links);
        $this->assertSame(MatchClassification::Partial, $result->suggestions[0]->classification);
    }

    public function test_each_instalment_of_the_plan_takes_one_payment(): void
    {
        $twoOfThree = $this->match(
            [$this->authorization(1, 'GRAFICA SUL', 100000, ['balanceCents' => 60000, 'planAmounts' => [30000, 30000]])],
            [
                $this->payment(12, 'GRAFICA SUL', 30000, ['paidOn' => '2026-07-25']),
                $this->payment(10, 'GRAFICA SUL', 30000, ['paidOn' => '2026-07-10']),
                $this->payment(11, 'GRAFICA SUL', 30000, ['paidOn' => '2026-07-15']),
            ],
        );

        $onlyOneEntry = $this->match(
            [$this->authorization(1, 'GRAFICA SUL', 100000, ['planAmounts' => [40000, 30000, 30000]])],
            [$this->payment(10, 'GRAFICA SUL', 40000, ['paidOn' => '2026-07-10']), $this->payment(11, 'GRAFICA SUL', 40000, ['paidOn' => '2026-07-15'])],
        );

        $this->assertSame([[1, 10], [1, 11]], $this->ids($twoOfThree->links));
        $this->assertSame([[1, 10]], $this->ids($onlyOneEntry->links));
        $this->assertSame([[1, 11]], $this->ids($onlyOneEntry->suggestions));
    }

    public function test_the_plan_prevails_over_the_condition_and_over_earlier_payments(): void
    {
        $overCondition = $this->match(
            [$this->authorization(1, 'GRAFICA SUL', 100000, ['installments' => 2, 'planAmounts' => [40000, 30000, 30000]])],
            [$this->payment(10, 'GRAFICA SUL', 50000)],
        );

        $overEarlierPayments = $this->match(
            [$this->authorization(1, 'GRAFICA SUL', 100000, ['balanceCents' => 75000, 'installmentAmounts' => [25000], 'planAmounts' => [40000, 30000, 30000]])],
            [$this->payment(10, 'GRAFICA SUL', 25000)],
        );

        $this->assertSame([], $overCondition->links);
        $this->assertSame([], $overEarlierPayments->links);
    }

    public function test_an_empty_plan_links_no_instalment(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'GRAFICA SUL', 90000, ['balanceCents' => 30000, 'installments' => 3, 'planAmounts' => []])],
            [$this->payment(10, 'GRAFICA SUL', 10000)],
        );

        $this->assertSame([], $result->links);
    }

    public function test_the_whole_amount_still_links_by_the_general_rule(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'GRAFICA SUL', 100000, ['planAmounts' => [40000, 30000, 30000]])],
            [$this->payment(10, 'GRAFICA SUL', 100000)],
        );

        $this->assertSame(MatchClassification::Automatic, $result->links[0]->classification);
    }

    public function test_plan_instalments_keep_the_protections_of_equal_instalments(): void
    {
        $otherCard = $this->match(
            [$this->authorization(1, 'GRAFICA SUL', 100000, ['planAmounts' => [40000, 30000, 30000], 'card' => '0798', 'paysByCard' => true])],
            [$this->payment(10, 'GRAFICA SUL', 40000, ['card' => '4931'])],
        );

        $twoAuthorizations = $this->match(
            [
                $this->authorization(1, 'GRAFICA SUL', 100000, ['planAmounts' => [40000, 30000, 30000]]),
                $this->authorization(2, 'GRAFICA SUL', 80000, ['planAmounts' => [40000, 40000]]),
            ],
            [$this->payment(10, 'GRAFICA SUL', 40000)],
        );

        $weakSupplier = $this->match(
            [$this->authorization(1, 'PAPELARIA CENTRAL', 100000, ['planAmounts' => [40000, 30000, 30000]])],
            [$this->payment(10, 'PAPELARIA DO BAIRRO', 40000)],
        );

        $this->assertSame([], $otherCard->links);
        $this->assertTrue($otherCard->suggestions[0]->cardMismatch);
        $this->assertSame([], $twoAuthorizations->links);
        $this->assertSame([], $weakSupplier->links);
    }

    public function test_plan_links_never_pass_the_authorized_amount(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'GRAFICA SUL', 100000, ['planAmounts' => [40000, 30000, 30000]])],
            [
                $this->payment(10, 'GRAFICA SUL', 40000, ['paidOn' => '2026-07-10']),
                $this->payment(11, 'GRAFICA SUL', 30000, ['paidOn' => '2026-07-11']),
                $this->payment(12, 'GRAFICA SUL', 30000, ['paidOn' => '2026-07-12']),
                $this->payment(13, 'GRAFICA SUL', 30000, ['paidOn' => '2026-07-13']),
                $this->payment(14, 'GRAFICA SUL', 40000, ['paidOn' => '2026-07-14']),
            ],
        );

        $this->assertSame([[1, 10], [1, 11], [1, 12]], $this->ids($result->links));
    }
}
