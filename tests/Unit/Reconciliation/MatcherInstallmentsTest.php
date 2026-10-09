<?php

namespace Tests\Unit\Reconciliation;

use App\Enums\MatchClassification;
use App\Services\Reconciliation\Matching\EngineParameters;
use App\Services\Reconciliation\Matching\Matcher;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\BuildsMatcherCandidates;

class MatcherInstallmentsTest extends TestCase
{
    use BuildsMatcherCandidates;

    public function test_a_payment_with_the_amount_of_one_instalment_is_linked(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'GRAFICA SUL', 90000, ['installments' => 3])],
            [$this->payment(10, 'GRAFICA SUL', 30000)],
        );

        $this->assertSame([[1, 10]], $this->ids($result->links));
        $this->assertSame(MatchClassification::Installment, $result->links[0]->classification);
        $this->assertSame(100, $result->links[0]->score->score);
        $this->assertSame(100, $result->links[0]->score->amountScore);
        $this->assertSame(0, $result->links[0]->score->differenceCents);
        $this->assertSame([], $result->suggestions);
    }

    public function test_instalment_within_the_tolerance_links_with_the_score_of_the_supplier(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'PAPELARIA CENTRAL', 90000, ['installments' => 3])],
            [$this->payment(10, 'PAPELARIA CENTRAU', 30040)],
        );

        $this->assertSame([[1, 10]], $this->ids($result->links));
        $this->assertSame(100, $result->links[0]->score->amountScore);
        $this->assertGreaterThanOrEqual(90, $result->links[0]->score->score);
        $this->assertLessThan(100, $result->links[0]->score->score);
        $this->assertSame(40, $result->links[0]->score->differenceCents);
    }

    public function test_instalment_outside_its_tolerance_waits_as_partial(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'GRAFICA SUL', 90000, ['installments' => 3])],
            [$this->payment(10, 'GRAFICA SUL', 30051)],
        );

        $this->assertSame([], $result->links);
        $this->assertSame([[1, 10]], $this->ids($result->suggestions));
        $this->assertSame(MatchClassification::Partial, $result->suggestions[0]->classification);
    }

    public function test_supplier_below_the_automatic_threshold_is_not_linked_as_instalment(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'PAPELARIA CENTRAL', 90000, ['installments' => 3])],
            [$this->payment(10, 'PAPELARIA DO BAIRRO', 30000)],
        );

        $this->assertSame([], $result->links);
    }

    public function test_an_amount_that_is_not_an_instalment_waits_as_partial(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'GRAFICA SUL', 90000, ['installments' => 3])],
            [$this->payment(10, 'GRAFICA SUL', 45000)],
        );

        $this->assertSame([], $result->links);
        $this->assertSame(MatchClassification::Partial, $result->suggestions[0]->classification);
    }

    public function test_the_whole_amount_links_by_the_general_rule(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'GRAFICA SUL', 90000, ['installments' => 3])],
            [$this->payment(10, 'GRAFICA SUL', 90000)],
        );

        $this->assertSame([[1, 10]], $this->ids($result->links));
        $this->assertSame(MatchClassification::Automatic, $result->links[0]->classification);
    }

    public function test_the_last_instalment_settles_by_the_general_rule(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'GRAFICA SUL', 90000, ['balanceCents' => 30000, 'installments' => 3])],
            [$this->payment(10, 'GRAFICA SUL', 30000)],
        );

        $this->assertSame(MatchClassification::Automatic, $result->links[0]->classification);
    }

    public function test_a_condition_of_one_payment_gives_no_instalment(): void
    {
        foreach ([1, null] as $installments) {
            $result = $this->match(
                [$this->authorization(1, 'GRAFICA SUL', 100000, ['installments' => $installments])],
                [$this->payment(10, 'GRAFICA SUL', 10000)],
            );

            $this->assertSame([], $result->links);
            $this->assertSame(MatchClassification::Partial, $result->suggestions[0]->classification);
        }
    }

    public function test_a_payment_already_linked_as_still_owed_sets_the_instalment(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'GRAFICA SUL', 100000, ['balanceCents' => 90000, 'installments' => 1, 'installmentAmounts' => [10000]])],
            [$this->payment(10, 'GRAFICA SUL', 10000)],
        );

        $this->assertSame([[1, 10]], $this->ids($result->links));
        $this->assertSame(MatchClassification::Installment, $result->links[0]->classification);
    }

    public function test_several_instalments_are_linked_in_date_order_up_to_the_balance(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'GRAFICA SUL', 90000, ['balanceCents' => 60000, 'installments' => 3])],
            [
                $this->payment(12, 'GRAFICA SUL', 30000, ['paidOn' => '2026-07-25']),
                $this->payment(10, 'GRAFICA SUL', 30000, ['paidOn' => '2026-07-10']),
                $this->payment(11, 'GRAFICA SUL', 30000, ['paidOn' => '2026-07-15']),
            ],
        );

        $this->assertSame([[1, 10], [1, 11]], $this->ids($result->links));
    }

    public function test_division_with_a_cent_left_over_fits_the_tolerance(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'GRAFICA SUL', 100000, ['installments' => 3])],
            [
                $this->payment(10, 'GRAFICA SUL', 33333, ['paidOn' => '2026-07-10']),
                $this->payment(11, 'GRAFICA SUL', 33333, ['paidOn' => '2026-07-11']),
                $this->payment(12, 'GRAFICA SUL', 33334, ['paidOn' => '2026-07-12']),
            ],
        );

        $this->assertSame([[1, 10], [1, 11], [1, 12]], $this->ids($result->links));
    }

    public function test_an_instalment_that_does_not_fit_the_balance_waits_as_excess(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'GRAFICA SUL', 90000, ['balanceCents' => 20000, 'installments' => 3])],
            [$this->payment(10, 'GRAFICA SUL', 30000)],
        );

        $this->assertSame([], $result->links);
        $this->assertSame(MatchClassification::Excess, $result->suggestions[0]->classification);
    }

    public function test_a_payment_that_serves_two_authorizations_is_left_to_the_operator(): void
    {
        $result = $this->match(
            [
                $this->authorization(1, 'GRAFICA SUL', 90000, ['installments' => 3]),
                $this->authorization(2, 'GRAFICA SUL', 90000, ['installments' => 3]),
            ],
            [$this->payment(10, 'GRAFICA SUL', 30000)],
        );

        $this->assertSame([], $result->links);
        $this->assertSame([[1, 10], [2, 10]], $this->ids($result->suggestions));
    }

    public function test_a_payment_that_may_settle_another_authorization_is_not_taken_as_instalment(): void
    {
        $result = $this->match(
            [
                $this->authorization(1, 'GRAFICA SUL', 90000, ['installments' => 3]),
                $this->authorization(2, 'GRAFICA SUL', 30000),
                $this->authorization(3, 'GRAFICA SUL', 30000),
            ],
            [$this->payment(10, 'GRAFICA SUL', 30000)],
        );

        $this->assertSame([], $result->links);
    }

    public function test_instalment_paid_before_the_authorization_links_with_the_warning(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'GRAFICA SUL', 90000, ['installments' => 3])],
            [$this->payment(10, 'GRAFICA SUL', 30000, ['paidOn' => '2026-07-01'])],
        );

        $this->assertSame([[1, 10]], $this->ids($result->links));
        $this->assertTrue($result->links[0]->paidBeforeAuthorization);
    }

    public function test_instalment_on_another_card_is_left_to_the_operator(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'GRAFICA SUL', 90000, ['installments' => 3, 'card' => '0798', 'paysByCard' => true])],
            [$this->payment(10, 'GRAFICA SUL', 30000, ['card' => '4931'])],
        );

        $this->assertSame([], $result->links);
        $this->assertTrue($result->suggestions[0]->cardMismatch);
    }

    public function test_a_blocked_instalment_is_not_linked_again(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'GRAFICA SUL', 90000, ['installments' => 3])],
            [$this->payment(10, 'GRAFICA SUL', 30000), $this->payment(11, 'GRAFICA SUL', 30000, ['paidOn' => '2026-07-21'])],
            [Matcher::pairKey('A1', 'saude', 'P10')],
        );

        $this->assertSame([[1, 11]], $this->ids($result->links));
    }

    public function test_no_instalment_link_ever_passes_the_authorized_amount(): void
    {
        $parameters = new EngineParameters(toleranceCents: 50, toleranceBasisPoints: 100, toleranceCapCents: 20000);

        $result = $this->match(
            [$this->authorization(1, 'GRAFICA SUL', 100000, ['installments' => 4])],
            array_map(fn (int $id) => $this->payment($id, 'GRAFICA SUL', 25100, ['paidOn' => '2026-07-'.$id]), [10, 11, 12, 13, 14, 15]),
            parameters: $parameters,
        );

        $linked = count($result->links) * 25100;

        $this->assertLessThanOrEqual(100000 + $parameters->toleranceFor(100000), $linked);
        $this->assertGreaterThan(0, $linked);
    }
}
