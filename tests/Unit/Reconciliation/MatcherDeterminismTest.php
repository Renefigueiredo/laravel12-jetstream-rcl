<?php

namespace Tests\Unit\Reconciliation;

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

class MatcherDeterminismTest extends TestCase
{
    public function test_the_same_entries_in_any_order_give_the_same_result(): void
    {
        $suppliers = ['PADARIA PERNAMBUCANA', 'PAPELARIA CENTRAL', 'PAPELARIA CENTRAU', 'MERCADO LIVRE', 'FARMACIA BEIRA RIO', 'POSTO ALFA'];
        $branches = [
            'ALFA', 'BRAVO', 'CHARLIE', 'DELTA', 'ECHO', 'FOXTROT', 'GOLFE', 'HOTEL', 'INDIA', 'JULIETA',
            'KILO', 'LIMA', 'MIKE', 'NOVEMBRO', 'OSCAR', 'PAPAI', 'QUEBEC', 'ROMEU', 'SIERRA', 'TANGO',
        ];
        $authorizations = [];
        $payments = [];

        foreach (range(1, 60) as $index) {
            $authorizations[] = new AuthorizationCandidate(
                id: $index,
                supplier: $suppliers[$index % 6].' '.$branches[$index % 20],
                balanceCents: 5000 * (1 + $index % 5),
                authorizedCents: 5000 * (1 + $index % 5),
                authorizedOn: '2026-07-'.str_pad((string) (1 + $index % 28), 2, '0', STR_PAD_LEFT),
                identityKey: 'A'.$index,
                paysByCard: $index % 3 === 0,
                card: $index % 3 === 0 ? ['0798', '4931'][$index % 2] : null,
            );
        }

        foreach (range(1, 90) as $index) {
            $payments[] = new PaymentCandidate(
                id: 1000 + $index,
                supplier: $suppliers[$index % 6].' '.$branches[$index % 20],
                amountCents: 5000 * (1 + $index % 5) + ($index % 4) * 25,
                paidOn: '2026-07-'.str_pad((string) (1 + ($index * 3) % 28), 2, '0', STR_PAD_LEFT),
                unit: $index % 2 === 0 ? 'saude' : 'social',
                identityKey: 'P'.$index,
                card: $index % 4 === 0 ? ['0798', '4931'][$index % 3 === 0 ? 1 : 0] : null,
            );
        }

        $matcher = new Matcher(new SupplierSimilarity, new PairScorer, new PaymentMethodMatcher);
        $parameters = new EngineParameters;

        $reference = $this->fingerprint($matcher->match($authorizations, $payments, [], $parameters));

        $this->assertNotEmpty($reference['links']);
        $this->assertNotEmpty($reference['suggestions']);

        mt_srand(20260710);

        foreach (range(1, 5) as $ignored) {
            shuffle($authorizations);
            shuffle($payments);

            $this->assertSame($reference, $this->fingerprint($matcher->match($authorizations, $payments, [], $parameters)));
        }
    }

    /**
     * @return array{links: list<string>, suggestions: list<string>}
     */
    protected function fingerprint(MatchResult $result): array
    {
        $describe = fn (MatchedPair $pair): string => implode('|', [
            $pair->authorizationId,
            $pair->paymentId,
            $pair->classification->value,
            $pair->score->score,
            $pair->score->supplierScore,
            $pair->score->amountScore,
            $pair->position,
            (int) $pair->isTie,
            (int) $pair->paidBeforeAuthorization,
            (int) $pair->cardMismatch,
        ]);

        $links = array_map($describe, $result->links);
        sort($links);

        return ['links' => $links, 'suggestions' => array_map($describe, $result->suggestions)];
    }
}
