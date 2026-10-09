<?php

namespace App\Enums;

enum PairBlockReason: string
{
    case Rejected = 'rejected';
    case Unlinked = 'unlinked';

    public function label(): string
    {
        return __('conciliation.reconciliation.pair_block_reason.'.$this->value);
    }
}
