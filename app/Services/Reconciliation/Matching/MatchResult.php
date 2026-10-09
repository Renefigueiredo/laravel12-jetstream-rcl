<?php

namespace App\Services\Reconciliation\Matching;

final readonly class MatchResult
{
    /**
     * @param  list<MatchedPair>  $links  Pairs to link without human action, in the order to apply
     * @param  list<MatchedPair>  $suggestions  Pairs left for the operator
     */
    public function __construct(
        public array $links = [],
        public array $suggestions = [],
    ) {}
}
