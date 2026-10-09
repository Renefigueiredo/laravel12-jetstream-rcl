<?php

namespace Tests\Unit\Reconciliation;

use App\Enums\MatchClassification;
use App\Services\Reconciliation\Matching\AuthorizationCandidate;
use App\Services\Reconciliation\Matching\EngineParameters;
use App\Services\Reconciliation\Matching\MatchedPair;
use App\Services\Reconciliation\Matching\Matcher;
use App\Services\Reconciliation\Matching\MatchResult;
use App\Services\Reconciliation\Matching\PairScorer;
use App\Services\Reconciliation\Matching\PaymentCandidate;
use App\Services\Reconciliation\Matching\PaymentMethodMatcher;
use App\Services\Reconciliation\Matching\SupplierSimilarity;
use PHPUnit\Framework\TestCase;

class MatcherTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $extra
     */
    protected function authorization(int $id, string $supplier, int $cents, array $extra = []): AuthorizationCandidate
    {
        return new AuthorizationCandidate(...[
            'id' => $id,
            'supplier' => $supplier,
            'balanceCents' => $cents,
            'authorizedCents' => $cents,
            'authorizedOn' => '2026-07-05',
            'identityKey' => 'A'.$id,
            ...$extra,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function payment(int $id, string $supplier, int $cents, array $extra = []): PaymentCandidate
    {
        return new PaymentCandidate(...[
            'id' => $id,
            'supplier' => $supplier,
            'amountCents' => $cents,
            'paidOn' => '2026-07-20',
            'unit' => 'saude',
            'identityKey' => 'P'.$id,
            ...$extra,
        ]);
    }

    /**
     * @param  list<AuthorizationCandidate>  $authorizations
     * @param  list<PaymentCandidate>  $payments
     * @param  list<string>  $blocked
     */
    protected function match(array $authorizations, array $payments, array $blocked = [], ?EngineParameters $parameters = null): MatchResult
    {
        return (new Matcher(new SupplierSimilarity, new PairScorer, new PaymentMethodMatcher))
            ->match($authorizations, $payments, $blocked, $parameters ?? new EngineParameters);
    }

    /**
     * @param  list<MatchedPair>  $pairs
     * @return list<array{int, int}>
     */
    protected function ids(array $pairs): array
    {
        return array_map(fn (MatchedPair $pair): array => [$pair->authorizationId, $pair->paymentId], $pairs);
    }

    public function test_a_single_compatible_pair_is_linked(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'PADARIA PERNAMBUCANA', 125040)],
            [$this->payment(10, 'PADARIA PERNAMBUCANA COMERCIO', 125040), $this->payment(11, 'POSTO ALFA', 9900)],
        );

        $this->assertSame([[1, 10]], $this->ids($result->links));
        $this->assertSame([], $result->suggestions);
        $this->assertSame(MatchClassification::Automatic, $result->links[0]->classification);
        $this->assertSame(100, $result->links[0]->score->score);
    }

    public function test_difference_within_the_tolerance_is_linked_and_kept(): void
    {
        $result = $this->match([$this->authorization(1, 'PADARIA PERNAMBUCANA', 43000)], [$this->payment(10, 'PADARIA PERNAMBUCANA', 43030)]);

        $this->assertSame([[1, 10]], $this->ids($result->links));
        $this->assertSame(30, $result->links[0]->score->differenceCents);
    }

    public function test_similar_supplier_below_the_threshold_is_doubtful(): void
    {
        $result = $this->match([$this->authorization(1, 'MERCADO BOM PRECO CENTRO', 50000)], [$this->payment(10, 'MERCADO BOM PRXYO', 50000)]);

        $this->assertSame([], $result->links);
        $this->assertSame([[1, 10]], $this->ids($result->suggestions));
        $this->assertSame(MatchClassification::Doubtful, $result->suggestions[0]->classification);
        $this->assertFalse($result->suggestions[0]->isTie);
        $this->assertGreaterThanOrEqual(60, $result->suggestions[0]->score->score);
        $this->assertLessThan(90, $result->suggestions[0]->score->score);
    }

    public function test_partial_and_excess_wait_for_the_operator(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'PADARIA PERNAMBUCANA', 300000), $this->authorization(2, 'PAPELARIA CENTRAL', 50000)],
            [$this->payment(10, 'PADARIA PERNAMBUCANA', 100000), $this->payment(11, 'PAPELARIA CENTRAL', 65000)],
        );

        $this->assertSame([], $result->links);
        $this->assertSame(MatchClassification::Partial, $result->suggestions[0]->classification);
        $this->assertSame(-200000, $result->suggestions[0]->score->differenceCents);
        $this->assertSame(MatchClassification::Excess, $result->suggestions[1]->classification);
        $this->assertSame(15000, $result->suggestions[1]->score->differenceCents);
    }

    public function test_entries_without_a_compatible_pair_get_nothing(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'PADARIA PERNAMBUCANA', 125040)],
            [$this->payment(10, 'POSTO ALFA', 125040), $this->payment(11, 'PADARIA DO BAIRRO NOVO', 7700)],
        );

        $this->assertSame([], $result->links);
        $this->assertSame([], $result->suggestions);
    }

    public function test_two_identical_authorizations_and_one_payment_stay_tied(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'PADARIA PERNAMBUCANA', 50000), $this->authorization(2, 'PADARIA PERNAMBUCANA', 50000)],
            [$this->payment(10, 'PADARIA PERNAMBUCANA', 50000)],
        );

        $this->assertSame([], $result->links);
        $this->assertSame([[1, 10], [2, 10]], $this->ids($result->suggestions));
        $this->assertTrue($result->suggestions[0]->isTie);
        $this->assertTrue($result->suggestions[1]->isTie);
        $this->assertSame(MatchClassification::Doubtful, $result->suggestions[0]->classification);
    }

    public function test_two_identical_payments_for_one_authorization_stay_tied_at_the_same_position(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'PADARIA PERNAMBUCANA', 50000)],
            [$this->payment(10, 'PADARIA PERNAMBUCANA', 50000), $this->payment(11, 'PADARIA PERNAMBUCANA', 50000)],
        );

        $this->assertSame([], $result->links);
        $this->assertSame([[1, 10], [1, 11]], $this->ids($result->suggestions));
        $this->assertSame([1, 1], array_map(fn (MatchedPair $pair): int => $pair->position, $result->suggestions));
        $this->assertTrue($result->suggestions[0]->isTie);
    }

    public function test_tie_between_payments_is_broken_by_the_same_card(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'MERCADO LIVRE', 5120, ['paysByCard' => true, 'card' => '0798'])],
            [$this->payment(10, 'MERCADO LIVRE', 5120, ['card' => '4931']), $this->payment(11, 'MERCADO LIVRE', 5120, ['card' => '0798'])],
        );

        $this->assertSame([[1, 11]], $this->ids($result->links));
    }

    public function test_tie_on_the_same_card_stands(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'MERCADO LIVRE', 5120, ['paysByCard' => true, 'card' => '0798'])],
            [$this->payment(10, 'MERCADO LIVRE', 5120, ['card' => '0798']), $this->payment(11, 'MERCADO LIVRE', 5120, ['card' => '0798'])],
        );

        $this->assertSame([], $result->links);
        $this->assertCount(2, $result->suggestions);
    }

    public function test_tie_is_broken_by_the_payment_method_when_there_is_no_card_number(): void
    {
        $byCard = $this->match(
            [$this->authorization(1, 'MERCADO LIVRE', 5120, ['paysByCard' => true])],
            [$this->payment(10, 'MERCADO LIVRE', 5120), $this->payment(11, 'MERCADO LIVRE', 5120, ['card' => '0798'])],
        );
        $bySlip = $this->match(
            [$this->authorization(1, 'MERCADO LIVRE', 5120)],
            [$this->payment(10, 'MERCADO LIVRE', 5120), $this->payment(11, 'MERCADO LIVRE', 5120, ['card' => '0798'])],
        );
        $unbroken = $this->match(
            [$this->authorization(1, 'MERCADO LIVRE', 5120, ['paysByCard' => true])],
            [$this->payment(10, 'MERCADO LIVRE', 5120), $this->payment(11, 'MERCADO LIVRE', 5120)],
        );

        $this->assertSame([[1, 11]], $this->ids($byCard->links));
        $this->assertSame([[1, 10]], $this->ids($bySlip->links));
        $this->assertSame([], $unbroken->links);
    }

    public function test_tie_between_authorizations_is_broken_by_the_card_of_the_payment(): void
    {
        $result = $this->match(
            [
                $this->authorization(1, 'MERCADO LIVRE', 5120, ['paysByCard' => true, 'card' => '4931']),
                $this->authorization(2, 'MERCADO LIVRE', 5120, ['paysByCard' => true, 'card' => '0798']),
            ],
            [$this->payment(10, 'MERCADO LIVRE', 5120, ['card' => '0798'])],
        );

        $this->assertSame([[2, 10]], $this->ids($result->links));
        $this->assertSame([], $result->suggestions, 'The other authorization has no payment left and its only pair was on another card.');
    }

    public function test_different_cards_never_link_on_their_own(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'MERCADO LIVRE', 5120, ['paysByCard' => true, 'card' => '0798'])],
            [$this->payment(10, 'MERCADO LIVRE', 5120, ['card' => '4931'])],
        );

        $this->assertSame([], $result->links);
        $this->assertSame(MatchClassification::Doubtful, $result->suggestions[0]->classification);
        $this->assertTrue($result->suggestions[0]->cardMismatch);
    }

    public function test_card_rule_does_not_apply_when_only_one_side_names_a_card(): void
    {
        $paidOtherwise = $this->match(
            [$this->authorization(1, 'MERCADO LIVRE', 5120, ['paysByCard' => true, 'card' => '0798'])],
            [$this->payment(10, 'MERCADO LIVRE', 5120)],
        );
        $slipPaidByCard = $this->match(
            [$this->authorization(1, 'MERCADO LIVRE', 5120)],
            [$this->payment(10, 'MERCADO LIVRE', 5120, ['card' => '0798'])],
        );

        $this->assertSame([[1, 10]], $this->ids($paidOtherwise->links));
        $this->assertSame([[1, 10]], $this->ids($slipPaidByCard->links));
    }

    public function test_payment_before_the_authorization_links_and_carries_the_warning(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'PADARIA PERNAMBUCANA', 50000, ['authorizedOn' => '2026-07-15'])],
            [$this->payment(10, 'PADARIA PERNAMBUCANA', 50000, ['paidOn' => '2026-07-10'])],
        );

        $this->assertSame([[1, 10]], $this->ids($result->links));
        $this->assertTrue($result->links[0]->paidBeforeAuthorization);
        $this->assertSame([], $result->suggestions);
    }

    public function test_payment_before_the_authorization_that_is_only_doubtful_keeps_the_warning(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'MERCADO BOM PRECO CENTRO', 50000, ['authorizedOn' => '2026-07-15'])],
            [$this->payment(10, 'MERCADO BOM PRXYO', 50000, ['paidOn' => '2026-07-10'])],
        );

        $this->assertSame([], $result->links);
        $this->assertTrue($result->suggestions[0]->paidBeforeAuthorization);
    }

    public function test_among_equal_scores_the_closest_amount_wins(): void
    {
        $percent = new EngineParameters(toleranceBasisPoints: 100, toleranceCapCents: 20000);

        $twoAuthorizations = $this->match(
            [$this->authorization(1, 'PADARIA PERNAMBUCANA', 100500), $this->authorization(2, 'PADARIA PERNAMBUCANA', 100000)],
            [$this->payment(10, 'PADARIA PERNAMBUCANA', 100000)],
            [],
            $percent,
        );
        $twoPayments = $this->match(
            [$this->authorization(1, 'PADARIA PERNAMBUCANA', 100000)],
            [$this->payment(10, 'PADARIA PERNAMBUCANA', 100700), $this->payment(11, 'PADARIA PERNAMBUCANA', 99800)],
            [],
            $percent,
        );
        $equallyDistant = $this->match(
            [$this->authorization(1, 'PADARIA PERNAMBUCANA', 100000)],
            [$this->payment(10, 'PADARIA PERNAMBUCANA', 100300), $this->payment(11, 'PADARIA PERNAMBUCANA', 99700)],
            [],
            $percent,
        );

        $this->assertSame([[2, 10]], $this->ids($twoAuthorizations->links));
        $this->assertSame([[1, 11]], $this->ids($twoPayments->links));
        $this->assertSame(-200, $twoPayments->links[0]->score->differenceCents);
        $this->assertSame([], $equallyDistant->links);
        $this->assertTrue($equallyDistant->suggestions[0]->isTie);
    }

    public function test_capped_percentage_tolerance_links_small_differences_only(): void
    {
        $percent = new EngineParameters(toleranceBasisPoints: 100, toleranceCapCents: 20000);

        $result = $this->match(
            [
                $this->authorization(1, 'FORNECEDOR ALFA', 100000),
                $this->authorization(2, 'FORNECEDOR BRAVO', 100000),
                $this->authorization(3, 'FORNECEDOR CHARLIE', 5000000),
                $this->authorization(4, 'FORNECEDOR DELTA', 5000000),
            ],
            [
                $this->payment(10, 'FORNECEDOR ALFA', 101000),
                $this->payment(11, 'FORNECEDOR BRAVO', 98999),
                $this->payment(12, 'FORNECEDOR CHARLIE', 5020000),
                $this->payment(13, 'FORNECEDOR DELTA', 5020001),
            ],
            [],
            $percent,
        );

        $this->assertEqualsCanonicalizing([[1, 10], [3, 12]], $this->ids($result->links));
        $this->assertEqualsCanonicalizing([[2, 11], [4, 13]], $this->ids($result->suggestions));
    }

    public function test_payment_on_the_day_of_the_authorization_links(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'PADARIA PERNAMBUCANA', 50000, ['authorizedOn' => '2026-07-15'])],
            [$this->payment(10, 'PADARIA PERNAMBUCANA', 50000, ['paidOn' => '2026-07-15'])],
        );

        $this->assertSame([[1, 10]], $this->ids($result->links));
    }

    public function test_a_better_candidate_that_needs_a_human_holds_the_weaker_one_back(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'PAPELARIA CENTRAL', 50000, ['paysByCard' => true, 'card' => '0798'])],
            [
                $this->payment(10, 'PAPELARIA CENTRAL', 50000, ['card' => '4931']),
                $this->payment(11, 'PAPELARIA CENTRAU', 50000, ['card' => '0798']),
            ],
        );

        $this->assertSame([], $result->links);
        $this->assertSame([[1, 10], [1, 11]], $this->ids($result->suggestions));
        $this->assertSame([1, 2], array_map(fn (MatchedPair $pair): int => $pair->position, $result->suggestions));
    }

    public function test_a_blocked_pair_is_neither_linked_nor_suggested(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'PADARIA PERNAMBUCANA', 50000)],
            [$this->payment(10, 'PADARIA PERNAMBUCANA', 50000)],
            [Matcher::pairKey('A1', 'saude', 'P10')],
        );

        $this->assertSame([], $result->links);
        $this->assertSame([], $result->suggestions);
    }

    public function test_the_next_best_is_linked_once_the_best_is_taken(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'PAPELARIA CENTRAL', 50000), $this->authorization(2, 'PAPELARIA CENTRAU', 50000)],
            [$this->payment(10, 'PAPELARIA CENTRAL', 50000), $this->payment(11, 'PAPELARIA CENTRAU', 50000)],
        );

        $this->assertEqualsCanonicalizing([[1, 10], [2, 11]], $this->ids($result->links));
        $this->assertSame([], $result->suggestions);
    }

    public function test_suggestions_are_ranked_and_limited(): void
    {
        $payments = [
            $this->payment(10, 'PADARIA PERNAMBUCANA', 100000),
            $this->payment(11, 'PADARIA PERNAMBUCANA', 200000),
            $this->payment(12, 'PADARIA PERNAMBUCANA', 250000),
            $this->payment(13, 'PADARIA PERNAMBUCANA', 400000),
        ];

        $result = $this->match([$this->authorization(1, 'PADARIA PERNAMBUCANA', 300000)], $payments, [], new EngineParameters(suggestionsPerAuthorization: 3));

        $this->assertSame([[1, 12], [1, 11], [1, 13]], $this->ids($result->suggestions));
        $this->assertSame([1, 2, 2], array_map(fn (MatchedPair $pair): int => $pair->position, $result->suggestions));
    }

    public function test_settled_authorizations_are_not_matched(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'PADARIA PERNAMBUCANA', 50000, ['balanceCents' => 0])],
            [$this->payment(10, 'PADARIA PERNAMBUCANA', 50000)],
        );

        $this->assertSame([], $result->links);
        $this->assertSame([], $result->suggestions);
    }

    public function test_an_open_balance_is_what_is_compared(): void
    {
        $result = $this->match(
            [$this->authorization(1, 'PADARIA PERNAMBUCANA', 300000, ['balanceCents' => 100000])],
            [$this->payment(10, 'PADARIA PERNAMBUCANA', 100000)],
        );

        $this->assertSame([[1, 10]], $this->ids($result->links));
    }

    public function test_a_typing_mistake_in_a_one_word_name_is_found_by_the_amount(): void
    {
        $result = $this->match([$this->authorization(1, 'KALUNGA', 50000)], [$this->payment(10, 'KALUNGAS', 50000)]);

        $this->assertSame([[1, 10]], $this->ids($result->suggestions));
        $this->assertSame(MatchClassification::Doubtful, $result->suggestions[0]->classification);
    }

    public function test_no_automatic_link_ever_exceeds_the_tolerance(): void
    {
        $authorizations = [];
        $payments = [];

        foreach (range(1, 40) as $index) {
            $authorizations[] = $this->authorization($index, 'FORNECEDOR NUMERO '.$index, 10000 + $index * 100);
            $payments[] = $this->payment(100 + $index, 'FORNECEDOR NUMERO '.$index, 10000 + $index * 100 + ($index % 7) * 20);
        }

        $result = $this->match($authorizations, $payments);

        $this->assertNotEmpty($result->links);

        foreach ($result->links as $link) {
            $this->assertLessThanOrEqual(50, abs($link->score->differenceCents));
        }
    }
}
