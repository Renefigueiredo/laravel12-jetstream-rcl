<?php

namespace App\Services\Reconciliation\Matching;

use App\Enums\MatchClassification;

final class Matcher
{
    /**
     * Words shorter than this do not take part in the search for candidates.
     */
    private const INDEXED_WORD_LENGTH = 3;

    public function __construct(
        private SupplierSimilarity $similarity,
        private PairScorer $scorer,
        private PaymentMethodMatcher $methods,
    ) {}

    /**
     * Cross authorizations and payments. The same input always gives the same output:
     * nothing here reads the clock, the database or the order the entries arrived in.
     *
     * @param  list<AuthorizationCandidate>  $authorizations
     * @param  list<PaymentCandidate>  $payments
     * @param  list<string>  $blockedPairs  Keys from self::pairKey() of pairs the engine must leave alone
     * @param  (callable(int): void)|null  $reportProgress
     */
    public function match(array $authorizations, array $payments, array $blockedPairs, EngineParameters $parameters, ?callable $reportProgress = null): MatchResult
    {
        usort($authorizations, fn (AuthorizationCandidate $a, AuthorizationCandidate $b): int => $a->id <=> $b->id);
        usort($payments, fn (PaymentCandidate $a, PaymentCandidate $b): int => [$a->paidOn, $a->id] <=> [$b->paidOn, $b->id]);

        $authorizationsById = [];

        foreach ($authorizations as $authorization) {
            $authorizationsById[$authorization->id] = $authorization;
        }

        $paymentsById = [];

        foreach ($payments as $payment) {
            $paymentsById[$payment->id] = $payment;
        }

        $pairs = $this->candidatePairs($authorizations, $payments, array_fill_keys($blockedPairs, true), $parameters);

        $reportProgress !== null && $reportProgress(40);

        [$links, $linkedAuthorizations, $linkedPayments] = $this->linkExactPairs($pairs, $authorizationsById, $paymentsById, $parameters);

        $reportProgress !== null && $reportProgress(70);

        $suggestions = $this->suggest($pairs, $authorizations, $paymentsById, $linkedAuthorizations, $linkedPayments, $parameters);

        $reportProgress !== null && $reportProgress(100);

        return new MatchResult($links, $suggestions);
    }

    /**
     * The key that identifies a pair across runs, whatever ids its entries have.
     */
    public static function pairKey(string $authorizationIdentityKey, string $paymentUnit, string $paymentIdentityKey): string
    {
        return $authorizationIdentityKey."\n".$paymentUnit."\n".$paymentIdentityKey;
    }

    /**
     * Score only the pairs worth scoring: payments of suppliers sharing a word with the
     * authorization, and payments whose amount fits its balance.
     *
     * @param  list<AuthorizationCandidate>  $authorizations
     * @param  list<PaymentCandidate>  $payments
     * @param  array<string, true>  $blocked
     * @return array<int, array<int, MatchedPair>> Pairs by authorization id and payment id
     */
    private function candidatePairs(array $authorizations, array $payments, array $blocked, EngineParameters $parameters): array
    {
        $paymentsBySupplier = [];
        $suppliersByWord = [];

        foreach ($payments as $payment) {
            if (! isset($paymentsBySupplier[$payment->supplier])) {
                foreach ($this->indexedWords($payment->supplier) as $word) {
                    $suppliersByWord[$word][] = $payment->supplier;
                }
            }

            $paymentsBySupplier[$payment->supplier][] = $payment;
        }

        $byAmount = $payments;
        usort($byAmount, fn (PaymentCandidate $a, PaymentCandidate $b): int => [$a->amountCents, $a->id] <=> [$b->amountCents, $b->id]);

        $lowestSupplierScore = min($parameters->suggestionThreshold, $parameters->supplierThreshold);
        $pairs = [];

        foreach ($authorizations as $authorization) {
            if ($authorization->balanceCents <= 0) {
                continue;
            }

            $candidates = [];

            foreach ($this->indexedWords($authorization->supplier) as $word) {
                foreach ($suppliersByWord[$word] ?? [] as $supplier) {
                    if ($this->similarity->between($authorization->supplier, $supplier) < $lowestSupplierScore) {
                        continue;
                    }

                    foreach ($paymentsBySupplier[$supplier] as $payment) {
                        $candidates[$payment->id] = $payment;
                    }
                }
            }

            foreach ($this->withAmountNear($byAmount, $authorization->balanceCents, $parameters->toleranceFor($authorization->balanceCents)) as $payment) {
                $candidates[$payment->id] = $payment;
            }

            foreach ($candidates as $payment) {
                if (isset($blocked[self::pairKey($authorization->identityKey, $payment->unit, $payment->identityKey)])) {
                    continue;
                }

                $score = $this->scorer->score(
                    $this->similarity->between($authorization->supplier, $payment->supplier),
                    $authorization->balanceCents,
                    $payment->amountCents,
                    $parameters,
                );

                if ($score->classification === null) {
                    continue;
                }

                $pairs[$authorization->id][$payment->id] = new MatchedPair(
                    authorizationId: $authorization->id,
                    paymentId: $payment->id,
                    score: $score,
                    classification: $score->classification,
                    paidBeforeAuthorization: $payment->paidOn < $authorization->authorizedOn,
                    cardMismatch: $this->methods->cardsDiffer($authorization, $payment),
                );
            }
        }

        return $pairs;
    }

    /**
     * Link, round after round, each pair in which the payment is the single best of the
     * authorization and the authorization is the single best of the payment.
     *
     * @param  array<int, array<int, MatchedPair>>  $pairs
     * @param  array<int, AuthorizationCandidate>  $authorizationsById
     * @param  array<int, PaymentCandidate>  $paymentsById
     * @return array{0: list<MatchedPair>, 1: array<int, true>, 2: array<int, true>}
     */
    private function linkExactPairs(array $pairs, array $authorizationsById, array $paymentsById, EngineParameters $parameters): array
    {
        $links = [];
        $linkedAuthorizations = [];
        $linkedPayments = [];

        do {
            [$bestOfAuthorization, $bestOfPayment] = $this->winners($pairs, $authorizationsById, $paymentsById, $linkedAuthorizations, $linkedPayments, $parameters);

            $round = [];

            foreach ($bestOfAuthorization as $authorizationId => $paymentId) {
                if ($paymentId === null || ($bestOfPayment[$paymentId] ?? null) !== $authorizationId) {
                    continue;
                }

                $pair = $pairs[$authorizationId][$paymentId];

                if ($this->linksOnItsOwn($pair)) {
                    $round[] = $pair;
                }
            }

            foreach ($round as $pair) {
                $links[] = $pair;
                $linkedAuthorizations[$pair->authorizationId] = true;
                $linkedPayments[$pair->paymentId] = true;
            }
        } while ($round !== []);

        return [$links, $linkedAuthorizations, $linkedPayments];
    }

    /**
     * For each free authorization and each free payment, its single best contender, or null on a tie.
     *
     * @param  array<int, array<int, MatchedPair>>  $pairs
     * @param  array<int, AuthorizationCandidate>  $authorizationsById
     * @param  array<int, PaymentCandidate>  $paymentsById
     * @param  array<int, true>  $linkedAuthorizations
     * @param  array<int, true>  $linkedPayments
     * @return array{0: array<int, int|null>, 1: array<int, int|null>}
     *
     * Among equal scores the closest amount wins, so an exact pair is never held back by a near one.
     */
    private function winners(array $pairs, array $authorizationsById, array $paymentsById, array $linkedAuthorizations, array $linkedPayments, EngineParameters $parameters): array
    {
        $topOfAuthorization = [];
        $topOfPayment = [];

        foreach ($pairs as $authorizationId => $authorizationPairs) {
            if (isset($linkedAuthorizations[$authorizationId])) {
                continue;
            }

            foreach ($authorizationPairs as $paymentId => $pair) {
                if (isset($linkedPayments[$paymentId]) || ! $this->contends($pair, $parameters)) {
                    continue;
                }

                $this->keepTop($topOfAuthorization, $authorizationId, $paymentId, $pair->score->score, abs($pair->score->differenceCents));
                $this->keepTop($topOfPayment, $paymentId, $authorizationId, $pair->score->score, abs($pair->score->differenceCents));
            }
        }

        $bestOfAuthorization = [];

        foreach ($topOfAuthorization as $authorizationId => $top) {
            $bestOfAuthorization[$authorizationId] = count($top['ids']) === 1
                ? $top['ids'][0]
                : $this->methods->preferredPayment(
                    $authorizationsById[$authorizationId],
                    array_map(fn (int $id): PaymentCandidate => $paymentsById[$id], $top['ids']),
                )?->id;
        }

        $bestOfPayment = [];

        foreach ($topOfPayment as $paymentId => $top) {
            $bestOfPayment[$paymentId] = count($top['ids']) === 1
                ? $top['ids'][0]
                : $this->methods->preferredAuthorization(
                    $paymentsById[$paymentId],
                    array_map(fn (int $id): AuthorizationCandidate => $authorizationsById[$id], $top['ids']),
                )?->id;
        }

        return [$bestOfAuthorization, $bestOfPayment];
    }

    /**
     * What is left becomes suggestions, ranked for each authorization.
     *
     * @param  array<int, array<int, MatchedPair>>  $pairs
     * @param  list<AuthorizationCandidate>  $authorizations
     * @param  array<int, PaymentCandidate>  $paymentsById
     * @param  array<int, true>  $linkedAuthorizations
     * @param  array<int, true>  $linkedPayments
     * @return list<MatchedPair>
     */
    private function suggest(array $pairs, array $authorizations, array $paymentsById, array $linkedAuthorizations, array $linkedPayments, EngineParameters $parameters): array
    {
        $topScoreOfAuthorization = [];
        $topScoreOfPayment = [];
        $remaining = [];

        foreach ($authorizations as $authorization) {
            if (isset($linkedAuthorizations[$authorization->id])) {
                continue;
            }

            foreach ($pairs[$authorization->id] ?? [] as $paymentId => $pair) {
                if (isset($linkedPayments[$paymentId])) {
                    continue;
                }

                $remaining[$authorization->id][] = $pair;

                if ($this->contends($pair, $parameters)) {
                    $this->keepTop($topScoreOfAuthorization, $authorization->id, $paymentId, $pair->score->score, abs($pair->score->differenceCents));
                    $this->keepTop($topScoreOfPayment, $paymentId, $authorization->id, $pair->score->score, abs($pair->score->differenceCents));
                }
            }
        }

        $suggestions = [];

        foreach ($remaining as $authorizationId => $authorizationPairs) {
            usort($authorizationPairs, fn (MatchedPair $a, MatchedPair $b): int => [
                $b->score->withinTolerance, $b->score->score, $paymentsById[$a->paymentId]->paidOn, $a->paymentId,
            ] <=> [
                $a->score->withinTolerance, $a->score->score, $paymentsById[$b->paymentId]->paidOn, $b->paymentId,
            ]);

            $position = 0;
            $previous = null;

            foreach (array_slice($authorizationPairs, 0, $parameters->suggestionsPerAuthorization) as $pair) {
                $rank = [$pair->score->withinTolerance, $pair->score->score];

                if ($rank !== $previous) {
                    $position++;
                    $previous = $rank;
                }

                $isTie = $this->contends($pair, $parameters) && (
                    $this->sharesTheTop($topScoreOfAuthorization[$authorizationId] ?? null, $pair)
                    || $this->sharesTheTop($topScoreOfPayment[$pair->paymentId] ?? null, $pair)
                );

                $suggestions[] = new MatchedPair(
                    authorizationId: $pair->authorizationId,
                    paymentId: $pair->paymentId,
                    score: $pair->score,
                    classification: $pair->classification === MatchClassification::Automatic ? MatchClassification::Doubtful : $pair->classification,
                    paidBeforeAuthorization: $pair->paidBeforeAuthorization,
                    cardMismatch: $pair->cardMismatch,
                    isTie: $isTie,
                    position: $position,
                );
            }
        }

        return $suggestions;
    }

    /**
     * A pair disputes the place of best match when its amount fits and its score deserves a suggestion.
     */
    private function contends(MatchedPair $pair, EngineParameters $parameters): bool
    {
        return $pair->score->withinTolerance && $pair->score->score >= $parameters->suggestionThreshold;
    }

    /**
     * Only a pair classified as automatic and on the same card links alone. A payment made
     * before the authorization links too; the pair carries the warning.
     */
    private function linksOnItsOwn(MatchedPair $pair): bool
    {
        return $pair->classification === MatchClassification::Automatic && ! $pair->cardMismatch;
    }

    /**
     * Keep the best contenders of an entry: the highest score and, among those, the closest amount.
     *
     * @param  array<int, array{score: int, distance: int, ids: list<int>}>  $tops
     */
    private function keepTop(array &$tops, int $key, int $id, int $score, int $distance): void
    {
        $better = ! isset($tops[$key])
            || $score > $tops[$key]['score']
            || ($score === $tops[$key]['score'] && $distance < $tops[$key]['distance']);

        if ($better) {
            $tops[$key] = ['score' => $score, 'distance' => $distance, 'ids' => [$id]];

            return;
        }

        if ($score === $tops[$key]['score'] && $distance === $tops[$key]['distance']) {
            $tops[$key]['ids'][] = $id;
        }
    }

    /**
     * @param  array{score: int, distance: int, ids: list<int>}|null  $top
     */
    private function sharesTheTop(?array $top, MatchedPair $pair): bool
    {
        return $top !== null
            && $top['score'] === $pair->score->score
            && $top['distance'] === abs($pair->score->differenceCents)
            && count($top['ids']) > 1;
    }

    /**
     * @return list<string>
     */
    private function indexedWords(string $supplier): array
    {
        return array_values(array_unique(array_filter(
            explode(' ', $supplier),
            fn (string $word): bool => strlen($word) >= self::INDEXED_WORD_LENGTH,
        )));
    }

    /**
     * Payments whose amount is within the tolerance of a balance, found by binary search.
     *
     * @param  list<PaymentCandidate>  $byAmount  Payments in ascending order of amount
     * @return list<PaymentCandidate>
     */
    private function withAmountNear(array $byAmount, int $balanceCents, int $tolerance): array
    {
        $lowest = $balanceCents - $tolerance;
        $highest = $balanceCents + $tolerance;
        $low = 0;
        $high = count($byAmount);

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);

            if ($byAmount[$middle]->amountCents < $lowest) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        $near = [];

        for ($index = $low; $index < count($byAmount) && $byAmount[$index]->amountCents <= $highest; $index++) {
            $near[] = $byAmount[$index];
        }

        return $near;
    }
}
