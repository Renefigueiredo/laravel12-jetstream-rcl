<?php

namespace Tests\Concerns;

use App\Services\Reconciliation\Matching\AuthorizationCandidate;
use App\Services\Reconciliation\Matching\EngineParameters;
use App\Services\Reconciliation\Matching\MatchedPair;
use App\Services\Reconciliation\Matching\Matcher;
use App\Services\Reconciliation\Matching\MatchResult;
use App\Services\Reconciliation\Matching\PairScorer;
use App\Services\Reconciliation\Matching\PaymentCandidate;
use App\Services\Reconciliation\Matching\PaymentMethodMatcher;
use App\Services\Reconciliation\Matching\SupplierSimilarity;

trait BuildsMatcherCandidates
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
}
